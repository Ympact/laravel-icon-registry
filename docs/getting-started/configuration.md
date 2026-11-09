# Configuration

Published to `config/icon-registry.php` by
`php artisan vendor:publish --tag=icon-registry-config`. The published file is merged over the
package defaults.

## Options

| Key | Default | Description |
| --- | --- | --- |
| `providers` | `flux`, `blade-icons` | Where icons come from, keyed by provider name. The name is what `Icon::$provider` and `IconExists::provider()` refer to. Order matters: when two providers list the same key, the first wins. |
| `providers.*.driver` | | The class that lists and draws the icons; it implements `Ympact\IconRegistry\Contracts\IconProvider`. The rest of the entry is passed to it as `$options`. |
| `component` | `registry-icon` | The Blade tag that draws a stored key, without its `x-` prefix. Run `php artisan view:clear` after changing it. Avoid `icon` when Blade Icons is installed: it registers `<x-icon>`. |
| `metadata` | `resource_path('icons/metadata.php')` | The application's labels, aliases and use cases, keyed by icon key. |
| `generic_icons` | chevrons, directional arrows, `x`, `x-mark`, `close`, `plus`, `minus` | Icons `icon-registry:scan --write` adds no use cases to. Edit the published list to extend it; an empty list turns this off. See [Adding the keywords to the metadata file](../usage/searching-and-metadata.md#adding-the-keywords-to-the-metadata-file) for how patterns match. |
| `purposes` | `resource_path('icons/purposes.php')` | The application's [purposes](../usage/storing-and-drawing-icons.md#draw-by-purpose): one icon and label per meaning. |
| `cache` | `base_path('bootstrap/cache/icon-registry.php')` | Where `optimize` and `icon-registry:cache` write the cache. |
| `editor_manifest` | `base_path('bootstrap/cache/icon-registry-editor.json')` | Where `icon-registry:export` writes the JSON that editor extensions read. See [Editor support](../usage/editor-support.md). |

A provider whose package is not installed is skipped, so both default providers can stay listed.

## The `flux` provider

Lists what `<flux:icon>` can draw. Requires `livewire/flux`.

| Key | Default | Description |
| --- | --- | --- |
| `providers.flux.path` | `resource_path('views/flux/icon')` | The application's icon directory. Each subdirectory is a vendor (`tabler/home.blade.php` is `tabler.home`); loose files go under `loose_vendor`. `index.blade.php`, an override of Flux's icon component, is not listed. |
| `providers.flux.loose_vendor` | `app` | The vendor for loose files in `path`: `save.blade.php` is `app.save`. |
| `providers.flux.flux_icons` | `true` | List the icons that ship with Flux, under the vendor `flux`. An application file of the same name is what Flux draws for that name, so `flux.home` keeps resolving and is listed next to `app.home`. |

## The `blade-icons` provider

Lists every set registered with Blade Icons, keyed by set name: the `o-home` icon of the
`heroicons` set is `heroicons.o-home`, drawn as `heroicon-o-home`. Requires
`blade-ui-kit/blade-icons`.

| Key | Default | Description |
| --- | --- | --- |
| `providers.blade-icons.sets` | `null` | Set names to list; `null` lists every registered set. |

The sets are read with Blade Icons' `Factory::all()`, which Blade Icons marks as internal. CI tests
the provider against the Blade Icons version in `composer.json`.

## Adding a provider

Add an entry with a `driver` and whatever options the driver reads. See
[Writing a provider](../advanced/custom-providers.md).

```php
'providers' => [
    'flux' => [/* ... */],
    'brand' => [
        'driver' => App\Icons\BrandIconProvider::class,
        'path' => resource_path('svg/brand'),
    ],
],
```
