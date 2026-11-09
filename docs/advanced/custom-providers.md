# Writing a provider

A provider tells the registry which icons exist and draws one. Write one for icons the built-in
providers do not know about, such as a directory of brand SVGs or another icon component library.

## The contract

```php
namespace Ympact\IconRegistry\Contracts;

interface IconProvider
{
    public function isAvailable(): bool;

    /** @return iterable<\Ympact\IconRegistry\DiscoveredIcon> */
    public function discover(): iterable;

    public function render(\Ympact\IconRegistry\Icon $icon, \Illuminate\View\ComponentAttributeBag $attributes): \Illuminate\Contracts\Support\Htmlable;
}
```

- `isAvailable()` returns false when the package the provider depends on is not installed; the
  registry then skips it.
- `discover()` yields a `DiscoveredIcon` for each icon: its vendor, its name, the reference your
  `render()` needs, and the file path if there is one. The registry makes the key
  `<vendor>.<name>` and adds the metadata.
- `render()` draws an icon the provider listed.

The provider is resolved from the container, with its config entry as `$options`.

## Example: a directory of SVGs

```php
namespace App\Icons;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Ympact\IconRegistry\Contracts\IconProvider;
use Ympact\IconRegistry\DiscoveredIcon;
use Ympact\IconRegistry\Icon;

class BrandIconProvider implements IconProvider
{
    public function __construct(protected array $options = []) {}

    public function isAvailable(): bool
    {
        return is_dir($this->options['path']);
    }

    public function discover(): iterable
    {
        foreach (glob($this->options['path'].'/*.svg') ?: [] as $file) {
            $name = basename($file, '.svg');

            yield new DiscoveredIcon(vendor: 'brand', name: $name, reference: $file, path: $file);
        }
    }

    public function render(Icon $icon, ComponentAttributeBag $attributes): Htmlable
    {
        $svg = file_get_contents($icon->reference);

        return new HtmlString(str_replace('<svg', '<svg '.$attributes->toHtml(), $svg));
    }
}
```

```php
// config/icon-registry.php
'providers' => [
    // ...
    'brand' => [
        'driver' => App\Icons\BrandIconProvider::class,
        'path' => resource_path('svg/brand'),
    ],
],
```

The icons are now `brand.<name>`, searchable, accepted by `IconExists` and drawn by
`<x-icon-registry::icon>`.

## Caching

The cache stores what `discover()` returned, including the reference, so `render()` must not
depend on state that changes between the cache build and the request. Rebuild the cache whenever
the set of icons changes.
