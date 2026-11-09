<?php

namespace Ympact\IconRegistry\Providers;

use BladeUI\Icons\Factory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Factory as DiskFactory;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use SplFileInfo;
use Ympact\IconRegistry\Contracts\IconProvider;
use Ympact\IconRegistry\DiscoveredIcon;
use Ympact\IconRegistry\Icon;

/**
 * Lists the icons of every set registered with Blade Icons, keyed by set name, and draws them
 * with `svg()`. Reads the sets through Factory::all(), which Blade Icons marks as internal.
 */
class BladeIconsProvider implements IconProvider
{
    /**
     * @param  array{sets?: list<string>|null}  $options
     */
    public function __construct(
        protected Container $container,
        protected array $options = [],
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Factory::class);
    }

    /**
     * @return list<DiscoveredIcon>
     */
    public function discover(): array
    {
        $icons = [];

        foreach ($this->sets() as $set => $options) {
            $disk = $options['disk'] ?? null;

            foreach ($options['paths'] as $path) {
                foreach ($this->svgNames($path, $disk) as $name) {
                    $icons[] = new DiscoveredIcon(
                        vendor: $set,
                        name: $name,
                        reference: "{$options['prefix']}-{$name}",
                        path: $disk === null ? $path.'/'.str_replace('.', '/', $name).'.svg' : null,
                    );
                }
            }
        }

        return $icons;
    }

    /**
     * The icon names under a set path, a subdirectory joined with a dot as Blade Icons names it.
     * Scanned here rather than read from Blade Icons' manifest, which is memoised the first
     * time views resolve and so misses a set registered after that.
     *
     * @return list<string>
     */
    protected function svgNames(string $path, ?string $disk): array
    {
        $files = $disk === null
            ? array_map(fn (SplFileInfo $file) => $file->getPathname(), $this->container->make(Filesystem::class)->allFiles($path))
            : $this->container->make(DiskFactory::class)->disk($disk)->allFiles($path);

        $names = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.svg')) {
                $names[] = str_replace('/', '.', Str::of($file)->after(rtrim($path, '/').'/')->beforeLast('.svg')->toString());
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    public function render(Icon $icon, ComponentAttributeBag $attributes): Htmlable
    {
        return $this->container->make(Factory::class)->svg(
            $icon->reference,
            (string) $attributes->get('class', ''),
            $attributes->except('class')->getAttributes(),
        );
    }

    /**
     * The registered sets, narrowed to the configured ones when `sets` is given.
     *
     * @return array<string, array{prefix: string, paths: list<string>, disk?: string}>
     */
    protected function sets(): array
    {
        $sets = $this->container->make(Factory::class)->all();
        $only = $this->options['sets'] ?? null;

        return $only === null ? $sets : array_intersect_key($sets, array_flip($only));
    }
}
