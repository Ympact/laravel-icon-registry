# Icon Registry

A searchable catalogue of the icons a Laravel application can draw. Providers list the icons
(Flux and Blade Icons ship with the package), the application adds labels and aliases in a
metadata file, and the registry offers search, a validation rule for stored icon keys, a Blade
component that draws a key, and a cache that `php artisan optimize` builds.

It is for applications that let people choose an icon: a menu editor, a category form, a
status badge. The registry holds no UI; build a picker on its search API.

## Getting started

- [Installation](getting-started/installation.md)
- [Configuration](getting-started/configuration.md)

## Usage

- [Storing and drawing icons](usage/storing-and-drawing-icons.md)
- [Searching and metadata](usage/searching-and-metadata.md)
- [Icons built with ympact/flux-icons](usage/flux-icons.md)
- [Editor support](usage/editor-support.md)

## Examples

- [Building an icon picker](examples/icon-picker.md)

## Advanced

- [Writing a provider](advanced/custom-providers.md)

## Reference

- [Artisan commands](reference/commands.md)
- [IconRegistry API](reference/icon-registry.md)

## Project

- [Roadmap](roadmap.md)

<!-- Every page under docs/ is linked from here; tests/Feature/Docs/DocsIndexTest.php fails on one that is not. New pages start from docs/stubs/. -->
