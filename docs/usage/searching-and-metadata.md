# Searching and metadata

## Search

```php
use Ympact\IconRegistry\Facades\Icons;

Icons::search('dustbin');                        // best match first
Icons::search('trash', vendor: 'tabler');
Icons::search('home', provider: 'blade-icons', limit: 20);
Icons::search();                                 // everything, in label order
```

`search()` returns a collection of `Ympact\IconRegistry\Icon`. Every word of the query must match
the icon somewhere: "remove document" finds an icon labelled "Remove document" and not one
that only matches "remove". Results are ordered by how well they matched:

- an exact word beats a prefix, and a prefix beats a match inside a word;
- a match on the label or the icon's own name beats one on an alias, which beats one on a use case;
- typing the icon's name or vendor ("tabler home") breaks ties in that icon's favour.

Only when nothing matches outright does search fall back to typo tolerance: one edit for a word
of four or five characters, two up to eight, three beyond. A swapped pair of letters ("trahs")
counts as one edit. Words under four characters are never corrected.

## The metadata file

`resources/icons/metadata.php` makes icons findable by the words your users type. It is part of
your application and belongs in version control.

```php
<?php

return [

    // ----------------------------------------------------------
    // tabler.*: our default set
    // ----------------------------------------------------------

    'tabler.trash' => [
        'label' => 'Remove',
        'aliases' => ['delete', 'dustbin'],
        'use_cases' => ['action.delete'],
    ],

];
```

| Field | Used for |
| --- | --- |
| `label` | The name shown for the icon; it defaults to the icon name as a headline ("Device Floppy"). |
| `aliases` | Other words for the icon. |
| `use_cases` | Where the application uses it, such as `action.delete`. Searchable, at a lower weight. |

Other fields are kept as they are but not searched.

When a cache exists, edits to this file take effect after `php artisan icon-registry:cache`.
`icon-registry:doctor` reports a cache that no longer matches as stale.

## Keeping it tidy

`php artisan icon-registry:doctor` lists metadata for icons that no longer exist and fails when it
finds any, so it can guard CI. `--prune-meta` (or `--fix`) removes those entries and rewrites the
file. The rewrite keeps the file header, section banners (a comment block ruled with `---`) and the
comment above each entry. A section banner whose first entry is pruned moves to the next entry of
that vendor.

A file whose `return [` is not on a line of its own, such as `var_export()` output, keeps whatever
precedes `return` but loses the comments inside the array.

## Finding the icons you use

`php artisan icon-registry:scan` lists the icons your source refers to, how often, and keywords
describing their context. Use the keywords as a starting point for `aliases` and `use_cases`, or
let `--write` add them (see below).

It recognises:

- `<flux:icon.home />`, `<flux:icon name="home" />`, and `icon`, `icon:leading` and `icon:trailing`
  (or `icon-leading`, `icon-trailing`) on any Flux or `x-` component;
- `<x-registry-icon icon="tabler.home" />` (or the tag set in `component`), `<x-icon name="..." />` and Blade Icons
  components such as `<x-heroicon-o-home />`;
- `@svg('...')`, `svg('...')` and `Icons::render('...')`;
- in PHP, `'icon' => '...'`, `$icon = '...'`, `icon: '...'`, `->icon('...')`, and any string that
  is exactly a registry key, unless it is the first argument of a lookup such as `route()`,
  `config()`, `view()` or `__()`.

Keywords come from the reference's `label`, `title`, `aria-label`, `tooltip`, `heading`,
`placeholder`, `href` and `wire:click`, its slot or the text after it on the same line, a label
in the same PHP array or fluent chain, and more weakly the file's path. Only the strings inside a
Blade echo count, so `{{ __('Delete') }}` gives "delete" and `{{ $user->name }}` gives nothing.
Words in the icon's own key are left out.

Matching is by pattern, so treat the result as a good approximation. Two groups are listed apart:

- **Not listed by any provider**: a literal name no provider knows, in a place that can only be an
  icon. These usually fail when rendered. `<x-card icon="foo">` is not reported, since `icon` may
  mean something else there.
- **Chosen at runtime**: `:icon="$item->icon"` cannot be resolved. A conditional between literals
  such as `:icon="$open ? 'chevron-up' : 'chevron-down'"` is resolved to both.

It does not see icons named outside those forms, such as PHP enum cases (Filament's `Heroicon::Home`) or names
built by concatenation, and icons stored in your database are not in the source at all.

### Adding the keywords to the metadata file

`php artisan icon-registry:scan --write` shows the use cases it would add and asks before changing
the file. `--force` skips the question. It only appends to `use_cases`, and:

- takes the five strongest keywords per icon, and only those found at the reference itself, never
  words from the file path;
- leaves out a word the entry's label, aliases or use cases already contain, so a second run adds
  nothing new unless the source changed;
- creates an entry for an icon that has none, and rebuilds the cache if one exists.

Existing entries keep their order and comments. A new entry goes after the last one of its vendor;
a vendor new to the file goes at the top, above any section banner. The keywords are single words taken from your
source, not curated use cases like `action.delete`, so review the diff before committing it.
