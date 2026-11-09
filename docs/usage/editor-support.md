# Editor support

The VS Code extension in `editors/vscode` completes icon keys as you type, matching keys, labels,
aliases and use cases, so you do not have to search with Artisan first. It completes:

- `<x-registry-icon icon="tabler.ho" />` and `:icon="'tabler.ho'"`, under whichever tag the
  `component` config key names;
- `Icons::find('...')` and `Icons::render('...')`.

The command **Icon Registry: Search and Insert Icon Key** inserts a key anywhere. Editor search
matches substrings only; typo tolerance stays in the PHP search.

## Install

The extension is not on the Marketplace yet. Build it from a checkout of this repository, which
needs Node 20 or later:

```bash
cd editors/vscode
npm ci
npm run package
code --install-extension laravel-icon-registry-0.1.0.vsix
```

## How it stays current

An editor cannot run the registry directly, so `php artisan icon-registry:export` writes the icons
to a JSON file, the `editor_manifest` path. Laravel's `bootstrap/cache` is ignored by Git by
default, so the file stays out of your repository.

In a trusted workspace the extension runs that command when the file is missing and when your Flux
icons, metadata or config change. In an untrusted workspace it never runs PHP. Set
`iconRegistry.phpCommand` in your user settings if PHP is not on your path, for example
`["./vendor/bin/sail", "php"]`. Icons from Blade Icons sets or a custom provider are picked up on
the next export: add their directories to `iconRegistry.watch`, or run
**Icon Registry: Refresh Icons**.

The extension activates in folders where the package is installed. After a first
`composer require`, reload the window.

## Other editors

The manifest is plain JSON, so other editors can use it. Its format:

```json
{
    "version": 1,
    "component": "registry-icon",
    "icons": [
        {
            "key": "tabler.trash",
            "vendor": "tabler",
            "name": "trash",
            "label": "Remove",
            "provider": "flux",
            "reference": "tabler.trash",
            "path": "/app/resources/views/flux/icon/tabler/trash.blade.php",
            "aliases": ["delete"],
            "use_cases": ["action.delete"]
        }
    ]
}
```
