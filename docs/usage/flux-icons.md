# Icons built with ympact/flux-icons

[ympact/flux-icons](https://github.com/Ympact/flux-icons) builds icons from vendors such as Tabler
or Lucide into Blade files that `<flux:icon>` draws. It is a development tool: install it with
`--dev`, build the icons you need, and commit them.

It writes each vendor to its own subdirectory of `resources/views/flux/icon`, which is where the
`flux` provider looks. Icons you build are therefore listed under their vendor straight away:

```bash
php artisan flux-icons:build tabler --icons=confetti
php artisan icon-registry:search confetti     # tabler.confetti
```

When a cache exists, rebuild it after building icons, or the registry keeps serving the old set:

```bash
php artisan icon-registry:cache
```

The validation rule does not need this: it checks the providers directly before rejecting a key
the cache does not know.
