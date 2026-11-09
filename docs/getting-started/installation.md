# Installation

```bash
composer require ympact/laravel-icon-registry
```

Install it as a normal dependency, not with `--dev`: the registry is read at runtime, by the
validation rule, the icon component and any picker you build.

The service provider registers itself through package discovery. The registry lists the icons of
whichever supported packages are installed, so with Flux or Blade Icons present it works without
further setup:

```bash
php artisan icon-registry:search home
```

## Publish the configuration

Only needed to change a provider, the metadata path or the cache path. See
[Configuration](configuration.md).

```bash
php artisan vendor:publish --tag=icon-registry-config
```

## Add metadata

Search matches icon names out of the box. Labels, aliases and use cases in
`resources/icons/metadata.php` make icons findable by the words your users type; see
[Searching and metadata](../usage/searching-and-metadata.md).

## Cache the registry on deploy

Without a cache, the registry asks every provider to scan its icons on each request that uses it.
The package hooks into Laravel's optimize command, so a deploy that already runs

```bash
php artisan optimize
```

builds the cache, and `php artisan optimize:clear` removes it. `icon-registry:cache` and
`icon-registry:clear` do the same on their own.

## Translations

The validation messages ship in English and Dutch. To change them, publish them:

```bash
php artisan vendor:publish --tag=icon-registry-lang
```
