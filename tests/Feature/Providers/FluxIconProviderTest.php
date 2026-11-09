<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux([
        '' => ['save'],
        'tabler' => ['home', 'trash'],
    ]);

    // Flux resolves components in a hashed view namespace, where an application's resources/views/flux comes first.
    app('view')->prependNamespace(hash('xxh128', 'flux'), dirname($this->workspace->fluxPath));
});

afterEach(fn () => $this->workspace->delete());

it('lists a vendor directory under the vendor, drawn by "<vendor>.<name>"', function (): void {
    $icon = app(IconRegistry::class)->find('tabler.home');

    expect($icon)->not->toBeNull()
        ->and($icon->provider)->toBe('flux')
        ->and($icon->reference)->toBe('tabler.home')
        ->and($icon->path)->toBe("{$this->workspace->fluxPath}/tabler/home.blade.php");
});

it('lists loose files under the loose vendor, drawn by their bare name', function (): void {
    $icon = app(IconRegistry::class)->find('app.save');

    expect($icon?->reference)->toBe('save');

    config()->set('icon-registry.providers.flux.loose_vendor', 'mine');
    app()->forgetScopedInstances();

    expect(app(IconRegistry::class)->all()->pluck('key'))->toContain('mine.save')->not->toContain('app.save');
});

it('does not list an override of the dynamic icon component as an icon', function (): void {
    $this->workspace->flux(['' => ['index']]);

    expect(app(IconRegistry::class)->find('app.index'))->toBeNull();
});

it('lists the icons that ship with Flux, unless an application file overrides one', function (): void {
    config()->set('icon-registry.providers.flux.flux_icons', true);

    $icons = app(IconRegistry::class)->all();

    expect($icons->firstWhere('key', 'flux.home')?->reference)->toBe('home')
        ->and($icons->pluck('reference')->filter(fn (string $reference) => $reference === 'home')->count())->toBe(1);

    // An application file named "home" is what <flux:icon icon="home"> draws, so Flux's own is not listed.
    File::put("{$this->workspace->fluxPath}/home.blade.php", '<svg/>');

    $fresh = app(IconRegistry::class)->icons(fresh: true);

    expect($fresh->pluck('key'))->toContain('app.home')->not->toContain('flux.home');
});

it('draws an icon through <flux:icon>, passing the attributes on', function (): void {
    $html = Blade::render('<x-icon-registry::icon icon="tabler.trash" class="size-5" />');

    expect($html)->toContain('data-icon="trash"')->toContain('class="size-5"');
});
