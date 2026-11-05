<?php

use Tests\Support\IconWorkspace;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux(['tabler' => ['home', 'trash']])
        ->metadata(['tabler.trash' => ['label' => 'Verwijderen', 'aliases' => ['prullenbak']]]);
});

afterEach(fn () => $this->workspace->delete());

it('prints a table of matches for a query', function (): void {
    artisan('icon-registry:search', ['query' => 'prullenbak'])
        ->expectsOutputToContain('tabler.trash')
        ->expectsOutputToContain('Showing 1 result(s).')
        ->assertSuccessful();
});

it('fails when nothing matches', function (): void {
    artisan('icon-registry:search', ['query' => 'zzzzzzzz'])->expectsOutputToContain('No icons found.')->assertFailed();
});

it('prints JSON without the search index', function (): void {
    artisan('icon-registry:search', ['query' => 'trash', '--json' => true])
        ->expectsOutputToContain('"key": "tabler.trash"')
        ->doesntExpectOutputToContain('"terms"')
        ->assertSuccessful();
});
