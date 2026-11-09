# Laravel Icon Registry for VS Code

Completes icon keys from [ympact/laravel-icon-registry](https://github.com/Ympact/laravel-icon-registry)
without running a command. Completion matches keys, labels, aliases and use cases.

It works in:

- the component tag: `<x-registry-icon icon="tabler.ho" />`, and `:icon="'tabler.ho'"`;
- `Icons::find('...')` and `Icons::render('...')`.

**Icon Registry: Search and Insert Icon Key** in the Command Palette inserts a key anywhere.

## How it stays current

The extension reads a JSON manifest that `php artisan icon-registry:export` writes. It runs that
command itself when the manifest is missing and when your icons, metadata or config change.

In an untrusted workspace it never runs PHP. Completion then uses a manifest that already exists.

## Settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `iconRegistry.manifestPath` | `bootstrap/cache/icon-registry-editor.json` | The manifest, relative to the workspace folder. |
| `iconRegistry.autoExport` | `true` | Run the export automatically. |
| `iconRegistry.phpCommand` | `["php"]` | How to run PHP, such as `["./vendor/bin/sail", "php"]`. User settings only. |
| `iconRegistry.watch` | Flux icons, metadata, config | Files that trigger an export. |

Changes to `manifestPath` and `watch` apply after reloading the window.

## Build

```bash
npm ci
npm test
npm run package
```

Install the resulting `.vsix` with **Extensions: Install from VSIX...**.
