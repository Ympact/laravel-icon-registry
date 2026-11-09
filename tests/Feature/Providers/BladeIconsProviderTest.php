<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\Blade;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

beforeEach(function (): void {
    $this->workspace = new IconWorkspace;

    app(Factory::class)
        ->add('heroicons', ['path' => $this->workspace->svgSet('heroicons', ['o-home', 's-trash']), 'prefix' => 'heroicon'])
        ->add('lucide', ['path' => $this->workspace->svgSet('lucide', ['house', 'arrows.up']), 'prefix' => 'lucide']);
});

afterEach(fn () => $this->workspace->delete());

it('lists every registered set under its set name, drawn by "<prefix>-<name>"', function (): void {
    $icon = app(IconRegistry::class)->find('heroicons.o-home');

    expect($icon)->not->toBeNull()
        ->and($icon->provider)->toBe('blade-icons')
        ->and($icon->reference)->toBe('heroicon-o-home')
        ->and($icon->path)->toBe("{$this->workspace->root}/svg/heroicons/o-home.svg")
        ->and(app(IconRegistry::class)->vendors()->all())->toBe(['heroicons', 'lucide']);
});

it('names an icon in a subdirectory with a dot, as Blade Icons does', function (): void {
    expect(app(IconRegistry::class)->find('lucide.arrows.up')?->reference)->toBe('lucide-arrows.up');
});

it('lists only the configured sets when sets are given', function (): void {
    config()->set('icon-registry.providers.blade-icons.sets', ['lucide']);

    expect(app(IconRegistry::class)->vendors()->all())->toBe(['lucide']);
});

it('draws an icon with svg(), passing the class and attributes on', function (): void {
    $html = Blade::render('<x-registry-icon icon="heroicons.s-trash" class="size-5" aria-hidden="true" />');

    expect($html)->toContain('data-icon="s-trash"')->toContain('class="size-5"')->toContain('aria-hidden="true"');
});
