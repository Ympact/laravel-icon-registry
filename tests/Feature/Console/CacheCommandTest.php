<?php

use Tests\Support\IconWorkspace;

use function Pest\Laravel\artisan;

beforeEach(fn () => $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home', 'trash']]));

afterEach(fn () => $this->workspace->delete());

it('writes the cache and says how many icons it holds', function (): void {
    artisan('icon-registry:cache')->expectsOutputToContain('Cached 2 icons.')->assertSuccessful();

    expect($this->workspace->cachePath)->toBeFile();
});
