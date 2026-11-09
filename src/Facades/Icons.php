<?php

namespace Ympact\IconRegistry\Facades;

use Illuminate\Support\Facades\Facade;
use Ympact\IconRegistry\IconRegistry;

/**
 * Static access to the icon registry for application code.
 *
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> all()
 * @method static \Ympact\IconRegistry\Icon|null find(string $key, bool $fresh = false)
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> icons(bool $fresh = false)
 * @method static \Illuminate\Support\Collection<int, string> vendors()
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> search(?string $query = null, ?string $vendor = null, int $limit = 50, bool $fresh = false, ?string $provider = null)
 * @method static \Illuminate\Contracts\Support\Htmlable render(string $key, \Illuminate\View\ComponentAttributeBag|array<string, mixed> $attributes = [])
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> build()
 * @method static array<string, \Ympact\IconRegistry\Contracts\IconProvider> providers()
 * @method static \Ympact\IconRegistry\Contracts\IconProvider provider(string $name)
 * @method static \Ympact\IconRegistry\MetadataFile metadata()
 * @method static string cachePath()
 * @method static int rebuildCache()
 * @method static string editorManifestPath()
 * @method static int writeEditorManifest(?string $path = null)
 * @method static bool clearCache()
 * @method static array<string, mixed> validate()
 * @method static array<string, mixed> cleanMetadata(bool $write = false)
 *
 * @see IconRegistry
 */
class Icons extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IconRegistry::class;
    }
}
