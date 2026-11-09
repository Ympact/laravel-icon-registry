# Searching and metadata

## Search

```php
use Ympact\IconRegistry\Facades\Icons;

Icons::search('prullenbak');                     // best match first
Icons::search('trash', vendor: 'tabler');
Icons::search('home', provider: 'blade-icons', limit: 20);
Icons::search();                                 // everything, in label order
```

`search()` returns a collection of `Ympact\IconRegistry\Icon`. Every word of the query must match
the icon somewhere: "bestand verwijderen" finds an icon labelled "Bestand verwijderen" and not one
that only matches "verwijderen". Results are ordered by how well they matched:

- an exact word beats a prefix, and a prefix beats a match inside a word;
- a match on the label or the icon's own name beats one on an alias, which beats one on a [purpose](storing-and-drawing-icons.md#draw-by-purpose);
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
        'label' => 'Verwijderen',
        'aliases' => ['delete', 'prullenbak'],
    ],

];
```

| Field | Used for |
| --- | --- |
| `label` | The name shown for the icon; it defaults to the icon name as a headline ("Device Floppy"). |
| `aliases` | Other words for the icon. |

Other fields are kept as they are but not searched.

## Keeping it tidy

`php artisan icon-registry:doctor` lists metadata for icons that no longer exist and fails when it
finds any, so it can guard CI. `--prune-meta` (or `--fix`) removes those entries and rewrites the
file. The rewrite keeps the file header, section banners (a comment block ruled with `---`) and the
comment above each entry. A section banner whose first entry is pruned moves to the next entry of
that vendor.

A file whose `return [` is not on a line of its own, such as `var_export()` output, keeps whatever
precedes `return` but loses the comments inside the array.
