<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;

/**
 * A throwaway directory of icon files, metadata and cache for one test, wired into the registry's
 * config. Each test gets its own directory, so the suite stays parallel-safe.
 */
class IconWorkspace
{
    public readonly string $root;

    public readonly string $fluxPath;

    public readonly string $metadataPath;

    public readonly string $purposesPath;

    public readonly string $cachePath;

    public function __construct()
    {
        $this->root = sys_get_temp_dir().'/icon-registry-'.uniqid('', true);
        $this->fluxPath = $this->root.'/views/flux/icon';
        $this->metadataPath = $this->root.'/icons/metadata.php';
        $this->purposesPath = $this->root.'/icons/purposes.php';
        $this->cachePath = $this->root.'/cache/icon-registry.php';

        File::ensureDirectoryExists($this->fluxPath);

        config()->set('icon-registry.metadata', $this->metadataPath);
        config()->set('icon-registry.purposes', $this->purposesPath);
        config()->set('icon-registry.cache', $this->cachePath);
        config()->set('icon-registry.providers.flux.path', $this->fluxPath);
        config()->set('icon-registry.providers.flux.flux_icons', false);
    }

    /**
     * Writes Flux-style icons: a vendor's names go in its subdirectory, '' means loose files.
     *
     * @param  array<string, list<string>>  $icons
     */
    public function flux(array $icons): self
    {
        foreach ($icons as $vendor => $names) {
            $directory = $vendor === '' ? $this->fluxPath : "{$this->fluxPath}/{$vendor}";
            File::ensureDirectoryExists($directory);

            foreach ($names as $name) {
                File::put("{$directory}/{$name}.blade.php", '<svg {{ $attributes }} data-icon="'.$name.'"></svg>');
            }
        }

        return $this;
    }

    /**
     * Writes SVG files for a Blade Icons set and returns the directory to register.
     *
     * @param  list<string>  $names  Dots become subdirectories, as Blade Icons reads them.
     */
    public function svgSet(string $set, array $names): string
    {
        $directory = "{$this->root}/svg/{$set}";

        foreach ($names as $name) {
            $file = $directory.'/'.str_replace('.', '/', $name).'.svg';
            File::ensureDirectoryExists(dirname($file));
            File::put($file, '<svg data-icon="'.$name.'"></svg>');
        }

        return $directory;
    }

    /**
     * @param  array<string, array<string, mixed>>  $metadata
     */
    public function metadata(array $metadata): self
    {
        File::ensureDirectoryExists(dirname($this->metadataPath));
        File::put($this->metadataPath, '<?php return '.var_export($metadata, true).';');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $purposes
     */
    public function purposes(array $purposes): self
    {
        File::ensureDirectoryExists(dirname($this->purposesPath));
        File::put($this->purposesPath, '<?php return '.var_export($purposes, true).';');

        // A test may rewrite the file after reading it, within opcache's revalidate window.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->purposesPath, true);
        }

        return $this;
    }

    public function delete(): void
    {
        File::deleteDirectory($this->root);
    }
}
