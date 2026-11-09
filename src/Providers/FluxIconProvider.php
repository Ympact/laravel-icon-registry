<?php

namespace Ympact\IconRegistry\Providers;

use Composer\InstalledVersions;
use Flux\Flux;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Ympact\IconRegistry\Contracts\IconProvider;
use Ympact\IconRegistry\DiscoveredIcon;
use Ympact\IconRegistry\Icon;

/**
 * Lists the icons `<flux:icon>` can draw: Flux's own, the loose files in the application's icon
 * directory, and one vendor per subdirectory there, which is where ympact/flux-icons builds to.
 */
class FluxIconProvider implements IconProvider
{
    /** Flux's dynamic `<flux:icon>` component itself, not an icon. */
    protected const COMPONENT_FILE = 'index';

    protected const EXTENSION = '.blade.php';

    /**
     * @param  array{path?: string, loose_vendor?: string, flux_icons?: bool}  $options
     */
    public function __construct(
        protected Filesystem $files,
        protected ViewFactory $views,
        protected array $options = [],
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Flux::class);
    }

    /**
     * @return list<DiscoveredIcon>
     */
    public function discover(): array
    {
        $path = $this->options['path'] ?? resource_path('views/flux/icon');
        $looseVendor = $this->options['loose_vendor'] ?? 'app';

        $loose = $this->iconsIn($path, fn (string $name) => new DiscoveredIcon($looseVendor, $name, $name, $this->file($path, $name)));

        $vendored = [];

        foreach ($this->files->isDirectory($path) ? $this->files->directories($path) : [] as $directory) {
            $vendor = basename($directory);

            array_push($vendored, ...$this->iconsIn($directory, fn (string $name) => new DiscoveredIcon($vendor, $name, "{$vendor}.{$name}", $this->file($directory, $name))));
        }

        return [...$this->bundledIcons($loose), ...$loose, ...$vendored];
    }

    public function render(Icon $icon, ComponentAttributeBag $attributes): Htmlable
    {
        return new HtmlString($this->views->make('icon-registry::providers.flux', [
            'reference' => $icon->reference,
            'attributes' => $attributes,
        ])->render());
    }

    /**
     * Flux's own icons. One an application file overrides stays listed, so a stored "flux.home"
     * keeps drawing, and points at that file because it is what Flux draws for the name.
     *
     * @param  list<DiscoveredIcon>  $loose
     * @return list<DiscoveredIcon>
     */
    protected function bundledIcons(array $loose): array
    {
        $path = $this->bundledPath();

        if (! ($this->options['flux_icons'] ?? true) || $path === null) {
            return [];
        }

        $overrides = array_column($loose, 'path', 'name');

        return $this->iconsIn($path, fn (string $name) => new DiscoveredIcon('flux', $name, $name, $overrides[$name] ?? $this->file($path, $name)));
    }

    protected function bundledPath(): ?string
    {
        $package = InstalledVersions::getInstallPath('livewire/flux');

        return $package === null ? null : $package.'/stubs/resources/views/flux/icon';
    }

    /**
     * @param  callable(string): DiscoveredIcon  $make
     * @return list<DiscoveredIcon>
     */
    protected function iconsIn(string $directory, callable $make): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $icons = [];

        foreach ($this->files->files($directory) as $file) {
            $filename = $file->getFilename();

            if (! str_ends_with($filename, self::EXTENSION)) {
                continue;
            }

            $name = substr($filename, 0, -strlen(self::EXTENSION));

            if ($name !== self::COMPONENT_FILE) {
                $icons[] = $make($name);
            }
        }

        return $icons;
    }

    protected function file(string $directory, string $name): string
    {
        return $directory.'/'.$name.self::EXTENSION;
    }
}
