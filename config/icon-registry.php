<?php

use Ympact\IconRegistry\Providers\BladeIconsProvider;
use Ympact\IconRegistry\Providers\FluxIconProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Where icons come from, keyed by provider name. A provider whose package
    | is not installed is skipped, so both can stay listed. When two providers
    | list the same "<vendor>.<name>" key, the one listed first wins.
    |
    */

    'providers' => [

        'flux' => [
            'driver' => FluxIconProvider::class,

            // The application's icon directory: loose files, plus one vendor per subdirectory.
            'path' => resource_path('views/flux/icon'),

            // The vendor the loose files in that directory are listed under.
            'loose_vendor' => 'app',

            // List the icons that ship with Flux, under the vendor "flux".
            'flux_icons' => true,
        ],

        'blade-icons' => [
            'driver' => BladeIconsProvider::class,

            // The Blade Icons sets to list, by set name; null lists every registered set.
            'sets' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Blade component
    |--------------------------------------------------------------------------
    |
    | The tag that draws a stored key, without its "x-" prefix. Run
    | `php artisan view:clear` after changing it. Blade Icons already
    | registers <x-icon>, so avoid that name when Blade Icons is installed.
    |
    */

    'component' => 'registry-icon',

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    |
    | Labels, aliases and use cases that make icons findable, keyed by icon key.
    | The file is part of the application and belongs in version control.
    |
    */

    'metadata' => resource_path('icons/metadata.php'),

    /*
    |--------------------------------------------------------------------------
    | Generic icons
    |--------------------------------------------------------------------------
    |
    | Icons used everywhere for the same mechanical reason, so the words near
    | them describe the screen, not the icon. `icon-registry:scan --write` adds
    | no use cases to them. Matches the icon name, ignoring a heroicons style
    | prefix ("o-x-mark"), or the whole key if the pattern has a dot. "*" works.
    |
    */

    'generic_icons' => [
        'chevron-*', 'chevrons-*',
        'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right',
        'x', 'x-mark', 'close',
        'plus', 'minus',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purposes
    |--------------------------------------------------------------------------
    |
    | What icons mean in this application, keyed by purpose: the one icon that
    | stands for it and its accessible label. Part of the application too.
    |
    */

    'purposes' => resource_path('icons/purposes.php'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Written by `php artisan optimize` and `icon-registry:cache`. Without it,
    | the registry asks every provider to scan its icons on each request.
    |
    */

    'cache' => base_path('bootstrap/cache/icon-registry.php'),

    /*
    |--------------------------------------------------------------------------
    | Editor manifest
    |--------------------------------------------------------------------------
    |
    | Where `icon-registry:export` writes the JSON that editor extensions
    | complete icon keys from. Generated, so keep it out of version control.
    |
    */

    'editor_manifest' => base_path('bootstrap/cache/icon-registry-editor.json'),

];
