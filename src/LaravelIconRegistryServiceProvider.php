<?php

namespace Ympact\LaravelIconRegistry;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the package with a Laravel application: its configuration, its publishable files
 * and its Artisan commands.
 */
class LaravelIconRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-icon-registry.php', 'laravel-icon-registry');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-icon-registry.php' => config_path('laravel-icon-registry.php'),
        ], 'laravel-icon-registry-config');

        if (is_dir(__DIR__.'/../resources/views')) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-icon-registry'),
            ], 'laravel-icon-registry-views');
        }

        $this->bootCommands();
    }

    protected function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([]);
    }
}
