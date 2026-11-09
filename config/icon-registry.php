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
    | Metadata
    |--------------------------------------------------------------------------
    |
    | Labels and aliases that make icons findable, keyed by icon key.
    | The file is part of the application and belongs in version control.
    |
    */

    'metadata' => resource_path('icons/metadata.php'),

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

];
