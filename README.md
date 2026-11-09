# Icon Registry

Keep icon use consistent across a Laravel application. Icon Registry is a searchable catalogue of
every icon your application can draw, across Flux, Blade Icons and your own providers, with one
place to decide which icon means what. Store an icon key, validate it, draw it, and let people find
icons by the words they actually type.

```php
Icons::search('dustbin');                    // tabler.trash, best match first

$request->validate(['icon' => ['nullable', new IconExists]]);
```

```blade
<x-registry-icon :icon="$item->icon" class="size-5" />
```

## Why use it

Without a shared source, the icon for "delete" gets decided again in every view: a trash can here,
a cross there, each named the way one icon set happens to name it. The registry gives those
decisions one home.

```php
// resources/icons/purposes.php
return [
    'action.delete' => ['icon' => 'tabler.trash', 'label' => 'Delete'],
];
```

```blade
<x-registry-icon purpose="action.delete" class="size-5" />
```

- **One icon per meaning.** Views ask for `action.delete`, not a specific icon, so every delete
  action looks the same and changing the icon is a one-line edit.
- **One accessible label per meaning.** A purpose carries its translatable label, so screen reader
  users hear the same name everywhere you ask for it with `labelled`.
- **One way to draw, whatever the icon set.** The same tag draws Flux, Blade Icons and custom icons,
  and the stored key does not change when you switch how an icon is drawn.
- **Accessible by default.** Icons are decorative unless you name them with `label`, so nobody has
  to remember `aria-hidden` in each view.
- **Mistakes are caught early.** `IconExists` rejects an unknown key when it is saved, and
  `icon-registry:doctor` fails in CI when a purpose or metadata entry refers to an icon that no
  longer exists.
- **You can see what is in use.** `icon-registry:scan` lists the icons your Blade and PHP source
  refers to, which helps spot near-duplicates before they spread.

## Features

- **Providers** for [Flux](https://fluxui.dev) (its own icons, your `resources/views/flux/icon`, and
  everything built there by [ympact/flux-icons](https://github.com/Ympact/flux-icons)) and for every
  [Blade Icons](https://github.com/blade-ui-kit/blade-icons) set. Both optional, and you can add your own.
- **Scored search** over names, labels, aliases, use cases and purposes, with typo tolerance.
- **A metadata file** for labels and aliases, which the doctor command prunes without losing your comments.
- **Purposes**: one icon and label per meaning (`action.delete`), so similar actions look the same across your app.
- **An `IconExists` rule** that suggests the closest icon.
- **A cache** that `php artisan optimize` builds. With opcache, drawing a few icons does not load the whole set.
- **A VS Code extension** that completes icon keys as you type. See [Editor support](docs/usage/editor-support.md).

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
