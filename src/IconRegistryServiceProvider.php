<?php

namespace Ympact\IconRegistry;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Ympact\IconRegistry\Console\CacheCommand;
use Ympact\IconRegistry\Console\ClearCommand;
use Ympact\IconRegistry\Console\DoctorCommand;
use Ympact\IconRegistry\Console\ScanCommand;
use Ympact\IconRegistry\Console\SearchCommand;

/**
 * Registers the icon registry with a Laravel application: its configuration, views, translations,
 * Artisan commands, and its place in `php artisan optimize`.
 */
class IconRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/icon-registry.php', 'icon-registry');

        // Scoped rather than singleton: the registry holds the icon set in memory, and scoped()
        // drops it between requests and queued jobs on long-lived workers.
        $this->app->scoped(IconRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'icon-registry');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'icon-registry');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'icon-registry');

        $this->publishes([
            __DIR__.'/../config/icon-registry.php' => config_path('icon-registry.php'),
        ], 'icon-registry-config');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/icon-registry'),
        ], 'icon-registry-lang');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CacheCommand::class,
            ClearCommand::class,
            DoctorCommand::class,
            ScanCommand::class,
            SearchCommand::class,
        ]);

        $this->optimizes(optimize: 'icon-registry:cache', clear: 'icon-registry:clear', key: 'icon-registry');
    }
}
