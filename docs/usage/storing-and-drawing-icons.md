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
<x-icon-registry::icon :icon="$item->icon" class="size-5" />
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

## Accessibility

An icon is decorative unless you give it an accessible name, so it renders with
`aria-hidden="true"`. Pass `label` when the icon carries meaning on its own:

```blade
<x-icon-registry::icon :icon="$item->icon" :label="__('Overdue')" />
```

```php
Icons::render('tabler.alert-circle', ['label' => __('Overdue')]);
```

| Passed | Rendered |
| --- | --- |
| nothing | `aria-hidden="true"` |
| `label="Overdue"` | `role="img" aria-label="Overdue"`; `label` itself is not rendered, and it replaces any `aria-hidden` |
| an empty or null `label` | decorative, so `:label="$maybeEmpty"` is safe |
| `aria-label` or `aria-labelledby` | those, plus `role="img"` and no `aria-hidden` |
| `title` | as given, not hidden; Blade Icons turns it into a `<title>` element with `role="img"` |
| `role` or `aria-hidden`, without a label | as given |

The registry does not fall back to the icon's metadata label: that names the picture ("Trash"),
not what it means where you use it ("Delete invoice"). In an icon-only button the icon stays
decorative and the button takes the name:

```blade
<button type="button" aria-label="{{ __('Delete invoice') }}">
    <x-icon-registry::icon icon="tabler.trash" class="size-5" />
</button>
```
