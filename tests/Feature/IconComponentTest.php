<?php

use Illuminate\Support\Facades\Blade;
use Tests\Support\IconWorkspace;

/**
 * The Blade component is the only thing most applications call, usually with a nullable column.
 */
beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux(['tabler' => ['home']]);

    app('view')->prependNamespace(hash('xxh128', 'flux'), dirname($this->workspace->fluxPath));
});

afterEach(fn () => $this->workspace->delete());

it('draws a key under its short tag', function (): void {
    expect(Blade::render('<x-registry-icon icon="tabler.home" class="size-5" />'))
        ->toContain('data-icon="home"')
        ->toContain('class="size-5"');
});

it('draws nothing for an icon that was never chosen', function (mixed $icon): void {
    expect(trim(Blade::render('<x-registry-icon :icon="$icon" />', ['icon' => $icon])))->toBe('');
})->with([null, '']);

it('no longer answers to the long tag', function (): void {
    Blade::render('<x-icon-registry::icon icon="tabler.home" />');
})->throws(InvalidArgumentException::class, 'Unable to locate a class or view for component');
