# Storing and drawing icons

Store the icon **key**, `<vendor>.<name>`, such as `tabler.home` or `heroicons.o-home`. A key is
unique across providers and does not change when you switch how an icon is drawn.

Each icon also has a **reference**: what its provider's own component expects. For Flux icons in a
vendor directory the two are the same (`tabler.home`), for Flux's own icons the reference is the
bare name (`home`), and for Blade Icons it is the prefixed name (`heroicon-o-home`). The registry
keeps track of it, so you never store it.

## Validate a stored key

```php
use Ympact\IconRegistry\Rules\IconExists;

$request->validate([
    'icon' => ['nullable', 'string', new IconExists],
]);
```

The rule fails for a key no provider lists, and suggests the closest one ("Did you mean
tabler.trash?"). Empty values pass; use `required` or `nullable` for those. Narrow the rule to one
vendor or one provider:

```php
IconExists::vendor('tabler');
IconExists::provider('blade-icons');
```

When a cache exists but predates the icon, the rule checks the providers directly before failing,
so a newly built icon is accepted without rebuilding the cache first.

## Draw a stored key

```blade
<x-icon-registry::icon :icon="$item->icon" class="size-5" aria-hidden="true" />
```

The component hands the attributes to the provider that listed the key: `<flux:icon>` for Flux
icons, `svg()` for Blade Icons. From PHP:

```php
use Ympact\IconRegistry\Facades\Icons;

Icons::render('tabler.home', ['class' => 'size-5']);
```

Drawing a key that no provider lists throws `Ympact\IconRegistry\Exceptions\UnknownIcon`, as Flux
and Blade Icons do for an unknown icon. Validate keys when they are saved, and run
`icon-registry:doctor` in CI to catch icons removed after that.

Decorative icons need `aria-hidden="true"`; an icon that carries meaning on its own needs an
accessible name, for example `role="img"` with an `aria-label`.
