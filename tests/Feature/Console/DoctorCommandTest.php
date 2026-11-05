<?php

use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

use function Pest\Laravel\artisan;

beforeEach(fn () => $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home']]));

afterEach(fn () => $this->workspace->delete());

it('passes when metadata and cache match the icons', function (): void {
    $this->workspace->metadata(['tabler.home' => ['label' => 'Huis']]);
    app(IconRegistry::class)->rebuildCache();

    artisan('icon-registry:doctor')->expectsOutputToContain('Icon cache is fresh.')->assertSuccessful();
});

it('fails on metadata for an icon that no longer exists, so it can guard CI', function (): void {
    $this->workspace->metadata(['tabler.gone' => ['label' => 'Weg']]);

    artisan('icon-registry:doctor')
        ->expectsOutputToContain('Metadata exists for missing icons:')
        ->expectsOutputToContain('tabler.gone')
        ->assertFailed();
});

it('prunes the metadata and rebuilds the cache with --fix', function (): void {
    $this->workspace->metadata(['tabler.gone' => ['label' => 'Weg'], 'tabler.home' => ['label' => 'Huis']]);

    artisan('icon-registry:doctor', ['--fix' => true])
        ->expectsOutputToContain('Pruned 1 stale metadata entries.')
        ->expectsOutputToContain('Rebuilt icon cache with 1 icons.')
        ->run();

    expect(require $this->workspace->metadataPath)->toBe(['tabler.home' => ['label' => 'Huis']])
        ->and($this->workspace->cachePath)->toBeFile();
});
