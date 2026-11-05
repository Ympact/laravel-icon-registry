<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Serves the workbench application's pages, for local development and the browser tests.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'workbench');

        Route::middleware('web')->group(__DIR__.'/../../routes/web.php');
    }
}
