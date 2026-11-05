<?php

namespace Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Boots the package inside the workbench application that testbench.yaml describes (the
 * package's provider and the workbench's), so tests see its routes and views as an application would.
 */
abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    /**
     * Registers the providers of the packages this one depends on (Livewire, Flux, Jolt...),
     * as an application's package discovery would; testbench leaves them out otherwise.
     */
    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        // Encryption (sessions, cookies, signed URLs) needs a key; nothing here outlives the run.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Parallel workers would otherwise share one compiled-view directory.
        if ($token = env('TEST_TOKEN')) {
            $app['config']->set('view.compiled', $app['config']->get('view.compiled').'/'.$token);
        }
    }
}
