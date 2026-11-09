# Icon Registry

A searchable catalogue of the icons a Laravel application can draw, across Flux, Blade Icons and
your own providers. Store an icon key, validate it, draw it, and let people find icons by the
words they actually type.

```php
Icons::search('prullenbak');                 // tabler.trash, best match first

$request->validate(['icon' => ['nullable', new IconExists]]);
```

```blade
<x-icon-registry::icon :icon="$item->icon" class="size-5" />
```

## Features

- **Providers** for [Flux](https://fluxui.dev) (its own icons, your `resources/views/flux/icon`, and
  everything built there by [ympact/flux-icons](https://github.com/Ympact/flux-icons)) and for every
  [Blade Icons](https://github.com/blade-ui-kit/blade-icons) set. Both optional, and you can add your own.
- **Scored search** over names, labels, aliases and use cases, with typo tolerance.
- **A metadata file** for labels and aliases, which the doctor command prunes without losing your comments.
- **An `IconExists` rule** that suggests the closest icon.
- **A cache** that `php artisan optimize` builds.

## Requirements

| | |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12 or 13 |
| Optional | `livewire/flux` 2, `blade-ui-kit/blade-icons` 1.10+ |

## Installation

```bash
composer require ympact/laravel-icon-registry
```

See [the documentation](docs/index.md) for configuration and usage.

## Development

```bash
composer test           # Pest
composer test:browser   # the Browser suite (installs Playwright's Chromium first)
composer analyse        # PHPStan, level 8
composer lint           # Pint
composer serve          # the workbench application, at http://localhost:8000
```

## License

MIT. See [LICENSE.md](LICENSE.md).
