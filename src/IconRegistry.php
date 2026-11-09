<?php

namespace Ympact\IconRegistry;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;
use Ympact\IconRegistry\Contracts\IconProvider;
use Ympact\IconRegistry\Exceptions\UnknownIcon;

/**
 * The catalogue of every icon the configured providers can draw, merged with the application's
 * metadata and searchable by key, label, alias and use case. Bound scoped, so it holds the set
 * for one request or job.
 */
class IconRegistry
{
    /** Bump when Icon::toArray() changes shape, so an older cache is rebuilt instead of misread. */
    protected const CACHE_VERSION = 1;

    /** Bump with SUPPORTED_MANIFEST_VERSION in editors/vscode/src/manifest.ts. */
    protected const EDITOR_MANIFEST_VERSION = 1;

    /**
     * Icons for the life of the request, so a picker searching per keystroke hydrates them once.
     *
     * @var Collection<int, Icon>|null
     */
    protected ?Collection $memoized = null;

    /** @var array<string, Icon> */
    protected array $byKey = [];

    /**
     * The cache file's records. Under opcache they are constant arrays in shared memory, so a
     * page drawing a few icons costs a few objects rather than one per icon in the set.
     *
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $records = null;

    protected bool $recordsRead = false;

    /** @var array<string, IconProvider>|null */
    protected ?array $providers = null;

    public function __construct(
        protected Container $container,
        protected Repository $config,
        protected Filesystem $files,
        protected IconSearch $search,
    ) {}

    /**
     * Every icon, from the cache when there is one and from the providers otherwise.
     *
     * @return Collection<int, Icon>
     */
    public function all(): Collection
    {
        if ($this->memoized !== null) {
            return $this->memoized;
        }

        $records = $this->cachedRecords();

        if ($records !== null) {
            return $this->memoized = collect(array_map(Icon::fromArray(...), array_values($records)));
        }

        $this->memoized = $this->build();
        $this->byKey = $this->memoized->keyBy('key')->all();

        return $this->memoized;
    }

    /**
     * @return Collection<int, Icon>
     */
    public function icons(bool $fresh = false): Collection
    {
        return $fresh ? $this->build() : $this->all();
    }

    public function find(string $key, bool $fresh = false): ?Icon
    {
        if ($fresh) {
            return $this->build()->first(fn (Icon $icon) => $icon->key === $key);
        }

        if (isset($this->byKey[$key])) {
            return $this->byKey[$key];
        }

        $records = $this->cachedRecords();

        if ($records === null) {
            $this->all();

            return $this->byKey[$key] ?? null;
        }

        return isset($records[$key]) ? $this->byKey[$key] = Icon::fromArray($records[$key]) : null;
    }

    /**
     * @return Collection<int, string>
     */
    public function vendors(): Collection
    {
        return $this->all()->pluck('vendor')->unique()->sort()->values();
    }

    /**
     * Icons matching every token of the query, best first; an empty query lists in label order.
     *
     * @return Collection<int, Icon>
     */
    public function search(
        ?string $query = null,
        ?string $vendor = null,
        int $limit = 50,
        bool $fresh = false,
        ?string $provider = null,
    ): Collection {
        $icons = array_filter($this->icons($fresh)->all(), fn (Icon $icon) => ($vendor === null || $icon->vendor === $vendor)
            && ($provider === null || $icon->provider === $provider));

        return collect($this->search->search($icons, $query, $limit));
    }

    /**
     * Draws a stored icon key with the provider that listed it.
     *
     * @param  ComponentAttributeBag|array<string, mixed>  $attributes
     *
     * @throws UnknownIcon when no provider lists the key.
     */
    public function render(string $key, ComponentAttributeBag|array $attributes = []): Htmlable
    {
        $icon = $this->find($key) ?? $this->find($key, fresh: true) ?? throw UnknownIcon::key($key);

        $attributes = is_array($attributes) ? new ComponentAttributeBag($attributes) : $attributes;

        return $this->provider($icon->provider)->render($icon, $attributes);
    }

    /**
     * Scans every available provider and merges the metadata. When two providers list the same
     * key, the one configured first wins.
     *
     * @return Collection<int, Icon>
     */
    public function build(): Collection
    {
        $metadata = $this->metadata()->read();

        // Keyed by hand: Collection::unique() compares each item with all before it, which is quadratic.
        $unique = [];

        foreach ($this->discover() as $found) {
            $unique[$found['key']] ??= $found;
        }

        $icons = array_map(fn (array $found) => $this->makeIcon($found['provider'], $found['key'], $found['icon'], $metadata), array_values($unique));

        // usort rather than a multi-column sortBy, whose data_get() per comparison dominated the build.
        usort($icons, fn (Icon $first, Icon $second) => $first->vendor <=> $second->vendor ?: $first->name <=> $second->name);

        return collect($icons);
    }

    /**
     * The configured providers whose packages are installed, by name.
     *
     * @return array<string, IconProvider>
     */
    public function providers(): array
    {
        return $this->providers ??= collect($this->config->array('icon-registry.providers', []))
            ->map(fn (array $options, string $name) => $this->makeProvider($name, $options))
            ->filter(fn (IconProvider $provider) => $provider->isAvailable())
            ->all();
    }

    /**
     * @throws InvalidArgumentException when the provider is not configured or not available.
     */
    public function provider(string $name): IconProvider
    {
        return $this->providers()[$name]
            ?? throw new InvalidArgumentException("Icon provider [{$name}] is not configured or its package is not installed.");
    }

    public function metadata(): MetadataFile
    {
        return new MetadataFile($this->files, $this->config->string('icon-registry.metadata'));
    }

    public function cachePath(): string
    {
        return $this->config->string('icon-registry.cache');
    }

    public function rebuildCache(): int
    {
        $this->forgetMemoized();

        $icons = $this->build();

        $this->files->ensureDirectoryExists(dirname($this->cachePath()));
        $this->files->replace($this->cachePath(), "<?php\n\nreturn ".var_export([
            'version' => self::CACHE_VERSION,
            'icons' => $this->recordsOf($icons),
        ], true).";\n");

        // Opcache would otherwise serve the previous cache to the next `require` in this process.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cachePath(), true);
        }

        return $icons->count();
    }

    public function editorManifestPath(): string
    {
        return $this->config->string('icon-registry.editor_manifest');
    }

    /**
     * Writes the icons editor extensions complete from. Always a fresh scan: the point is to
     * offer an icon as soon as it exists, whatever the cache holds.
     *
     * @return int The number of icons written.
     */
    public function writeEditorManifest(?string $path = null): int
    {
        $path ??= $this->editorManifestPath();
        $icons = $this->build();

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->replace($path, json_encode([
            'version' => self::EDITOR_MANIFEST_VERSION,
            'component' => $this->config->string('icon-registry.component'),
            'icons' => $icons,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $icons->count();
    }

    /**
     * @return bool False when there was no cache to delete.
     */
    public function clearCache(): bool
    {
        $this->forgetMemoized();

        if (! $this->files->exists($this->cachePath())) {
            return false;
        }

        return $this->files->delete($this->cachePath());
    }

    /**
     * Compares the icons on disk with the metadata and the cache.
     *
     * @return array{icon_count: int, metadata_count: int, cache_exists: bool, cache_is_fresh: bool, orphaned_metadata: list<string>, icons_without_metadata: list<string>, stale_cache_entries: list<string>, uncached_icons: list<string>, shadowed_icons: list<string>}
     */
    public function validate(): array
    {
        $fresh = $this->build();
        $freshKeys = $this->keysOf($fresh);
        $metadataKeys = array_keys($this->metadata()->read());
        $cached = $this->readCache();
        $cachedKeys = $this->sorted(array_keys($cached ?? []));

        return [
            'icon_count' => count($freshKeys),
            'metadata_count' => count($metadataKeys),
            'cache_exists' => $cached !== null,
            'cache_is_fresh' => $cached !== null && $cached === $this->recordsOf($fresh),
            'orphaned_metadata' => $this->sorted(array_diff($metadataKeys, $freshKeys)),
            'icons_without_metadata' => $this->sorted(array_diff($freshKeys, $metadataKeys)),
            'stale_cache_entries' => $this->sorted(array_diff($cachedKeys, $freshKeys)),
            'uncached_icons' => $this->sorted(array_diff($freshKeys, $cachedKeys)),
            'shadowed_icons' => $this->shadowedKeys(),
        ];
    }

    /**
     * Drops metadata for icons that no longer exist and normalises what is left.
     *
     * @return array{removed: list<string>, removed_count: int, metadata: array<string, array<string, mixed>>, written: bool}
     */
    public function cleanMetadata(bool $write = false): array
    {
        $file = $this->metadata();
        $metadata = $file->read();
        $validKeys = $this->build()->pluck('key')->flip();

        $removed = $this->sorted(array_filter(array_keys($metadata), fn (string $key) => ! $validKeys->has($key)));

        $cleaned = collect($metadata)
            ->filter(fn (array $entry, string $key) => $validKeys->has($key))
            ->map(fn (array $entry) => $file->normalizeEntry($entry))
            ->sortKeys()
            ->all();

        if ($write) {
            $file->write($cleaned);
        }

        return [
            'removed' => $removed,
            'removed_count' => count($removed),
            'metadata' => $cleaned,
            'written' => $write,
        ];
    }

    /**
     * @return Collection<int, array{provider: string, icon: DiscoveredIcon, key: string}>
     */
    protected function discover(): Collection
    {
        return collect($this->providers())->flatMap(fn (IconProvider $provider, string $name) => collect($provider->discover())
            ->map(fn (DiscoveredIcon $icon) => ['provider' => $name, 'icon' => $icon, 'key' => $this->keyFor($icon)]));
    }

    /**
     * Keys more than one provider lists; build() keeps the first provider's icon for each.
     *
     * @return list<string>
     */
    protected function shadowedKeys(): array
    {
        $counts = array_count_values($this->discover()->map(fn (array $found) => $found['key'])->all());

        return $this->sorted(array_keys(array_filter($counts, fn (int $count) => $count > 1)));
    }

    /**
     * @param  Collection<int, Icon>  $icons
     * @return list<string>
     */
    protected function keysOf(Collection $icons): array
    {
        return $this->sorted($icons->map(fn (Icon $icon) => $icon->key)->all());
    }

    /**
     * @param  array<array-key, string>  $keys
     * @return list<string>
     */
    protected function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }

    protected function keyFor(DiscoveredIcon $icon): string
    {
        return "{$icon->vendor}.{$icon->name}";
    }

    /**
     * @param  array<string, array<string, mixed>>  $metadata
     */
    protected function makeIcon(string $provider, string $key, DiscoveredIcon $found, array $metadata): Icon
    {
        $meta = $metadata[$key] ?? [];

        $label = (string) ($meta['label'] ?? Str::headline($found->name));
        $aliases = array_values($meta['aliases'] ?? []);
        $useCases = array_values($meta['use_cases'] ?? []);
        $terms = $this->search->termsFor($key, $found->vendor, $found->name, $label, $aliases, $useCases);

        return new Icon(
            key: $key,
            vendor: $found->vendor,
            name: $found->name,
            label: $label,
            provider: $provider,
            reference: $found->reference,
            path: $found->path,
            aliases: $aliases,
            useCases: $useCases,
            terms: $terms,
            searchText: implode("\n", array_keys($terms)),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidArgumentException when the driver does not implement IconProvider.
     */
    protected function makeProvider(string $name, array $options): IconProvider
    {
        $driver = $options['driver'] ?? throw new InvalidArgumentException("Icon provider [{$name}] has no driver.");

        $provider = $this->container->make($driver, ['options' => $options]);

        if (! $provider instanceof IconProvider) {
            throw new InvalidArgumentException("Icon provider [{$name}] must implement ".IconProvider::class.'.');
        }

        return $provider;
    }

    /**
     * @return array<string, array<string, mixed>>|null Null when no cache has been written.
     */
    protected function cachedRecords(): ?array
    {
        if (! $this->recordsRead) {
            $this->records = $this->readCache();
            $this->recordsRead = true;
        }

        return $this->records;
    }

    /**
     * @return array<string, array<string, mixed>>|null Null when there is no cache of this format.
     */
    protected function readCache(): ?array
    {
        if (! $this->files->exists($this->cachePath())) {
            return null;
        }

        $cached = $this->files->getRequire($this->cachePath());

        if (! is_array($cached) || ($cached['version'] ?? null) !== self::CACHE_VERSION || ! is_array($cached['icons'] ?? null)) {
            return null;
        }

        return $cached['icons'];
    }

    /**
     * @param  Collection<int, Icon>  $icons
     * @return array<string, array<string, mixed>>
     */
    protected function recordsOf(Collection $icons): array
    {
        return $icons->mapWithKeys(fn (Icon $icon) => [$icon->key => $icon->toArray()])->all();
    }

    protected function forgetMemoized(): void
    {
        $this->memoized = null;
        $this->byKey = [];
        $this->records = null;
        $this->recordsRead = false;
    }
}
