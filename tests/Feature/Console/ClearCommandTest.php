<?php

use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

use function Pest\Laravel\artisan;

beforeEach(fn () => $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home']]));

afterEach(fn () => $this->workspace->delete());

it('deletes the cache', function (): void {
    app(IconRegistry::class)->rebuildCache();

    artisan('icon-registry:clear')->expectsOutputToContain('Icon cache deleted.')->assertSuccessful();

    expect($this->workspace->cachePath)->not->toBeFile();
});

it('says so when there was no cache', function (): void {
    artisan('icon-registry:clear')->expectsOutputToContain('No icon cache existed.')->assertSuccessful();
});
