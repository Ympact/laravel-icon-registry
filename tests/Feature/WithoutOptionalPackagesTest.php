<?php

use BladeUI\Icons\Factory;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\Rules\IconExists;

use function Pest\Laravel\artisan;

/**
 * Flux and Blade Icons are optional. CI runs this file in a job that removes both; elsewhere it
 * is skipped, because with either installed there is nothing to prove.
 */
beforeEach(function (): void {
    if (class_exists(Flux::class) || class_exists(Factory::class)) {
        $this->markTestSkipped('Flux or Blade Icons is installed.');
    }

    $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home']]);
});

afterEach(fn () => isset($this->workspace) && $this->workspace->delete());

it('boots and lists nothing when no provider package is installed', function (): void {
    expect(app(IconRegistry::class)->providers())->toBe([])
        ->and(app(IconRegistry::class)->all())->toBeEmpty();
});

it('rejects every key, without a suggestion', function (): void {
    $error = Validator::make(['icon' => 'tabler.home'], ['icon' => [new IconExists]])->errors()->first('icon');

    expect($error)->toBe('The selected icon does not exist.');
});

it('runs its commands', function (): void {
    artisan('icon-registry:doctor')->expectsOutputToContain('none available')->assertSuccessful();
    artisan('icon-registry:cache')->expectsOutputToContain('Cached 0 icons.')->assertSuccessful();
});
