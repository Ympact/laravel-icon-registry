<?php

use Illuminate\Foundation\Console\OptimizeClearCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Tests\Support\IconWorkspace;

use function Pest\Laravel\artisan;

/**
 * Without its cache the registry scans every provider on each request, so `artisan optimize` has
 * to build it and `optimize:clear` has to drop it with the other caches.
 */
beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home']]);
});

afterEach(fn () => $this->workspace->delete());

/**
 * Every task but the registry's, so the commands leave testbench's own caches alone. Laravel's
 * own optimize keys are listed by hand: the method that returns them is protected.
 *
 * @param  list<string>  $keys
 */
function everyTaskButTheRegistry(array $keys): string
{
    return collect($keys)->reject(fn (string $key) => $key === 'icon-registry')->unique()->implode(',');
}

it('builds the icon cache with artisan optimize and clears it with optimize:clear', function (): void {
    $optimizeKeys = ['config', 'events', 'routes', 'views', ...array_keys(ServiceProvider::$optimizeCommands)];
    $clearKeys = array_keys(app(OptimizeClearCommand::class)->getOptimizeClearTasks());

    artisan('optimize', ['--except' => everyTaskButTheRegistry($optimizeKeys)])->assertSuccessful();

    expect($this->workspace->cachePath)->toBeFile();

    artisan('optimize:clear', ['--except' => everyTaskButTheRegistry($clearKeys)])->assertSuccessful();

    expect($this->workspace->cachePath)->not->toBeFile();
});

it('leaves the Blade Icons commands of the same purpose to Blade Icons', function (): void {
    $commands = Artisan::all();

    expect($commands['icons:cache']::class)->toStartWith('BladeUI\\')
        ->and($commands['icon-registry:cache']::class)->toStartWith('Ympact\\IconRegistry\\');
});
