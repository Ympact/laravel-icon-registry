<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeIconProvider;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Exceptions\UnknownIcon;
use Ympact\IconRegistry\IconRegistry;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux(['tabler' => ['home', 'trash']])
        ->metadata(['tabler.home' => ['label' => 'Huis', 'aliases' => ['woning']]]);
});

afterEach(fn () => $this->workspace->delete());

it('merges the metadata into the icons', function (): void {
    $icon = app(IconRegistry::class)->find('tabler.home');

    expect($icon?->label)->toBe('Huis')
        ->and($icon?->aliases)->toBe(['woning'])
        ->and(app(IconRegistry::class)->find('tabler.trash')?->label)->toBe('Trash');
});

it('reads the icons back from the cache it wrote', function (): void {
    expect(app(IconRegistry::class)->rebuildCache())->toBe(2);

    // A file added after the cache is invisible until the cache is rebuilt: that is the point of it.
    File::put("{$this->workspace->fluxPath}/tabler/star.blade.php", '<svg/>');
    app()->forgetScopedInstances();

    expect(app(IconRegistry::class)->find('tabler.home'))->toEqual(app(IconRegistry::class)->find('tabler.home', fresh: true))
        ->and(app(IconRegistry::class)->find('tabler.star'))->toBeNull()
        ->and(app(IconRegistry::class)->find('tabler.star', fresh: true))->not->toBeNull();
});

it('reads a rebuilt cache straight away, not the copy opcache still holds', function (): void {
    $registry = app(IconRegistry::class);
    $registry->rebuildCache();

    // Opcache leaves a file alone for its first seconds; an older cache is what it holds on to.
    touch($this->workspace->cachePath, time() - 60);
    $registry->all();

    File::put("{$this->workspace->fluxPath}/tabler/star.blade.php", '<svg/>');
    $registry->rebuildCache();

    expect($registry->all()->pluck('key'))->toContain('tabler.star');
})->skip(fn (): bool => ! filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL), 'Needs opcache.enable_cli, which CI turns on.');

it('ignores a cache it did not write and rebuilds from the providers', function (): void {
    File::ensureDirectoryExists(dirname($this->workspace->cachePath));
    File::put($this->workspace->cachePath, "<?php return [['key' => 'tabler.home']];");

    expect(app(IconRegistry::class)->all()->pluck('key')->all())->toBe(['tabler.home', 'tabler.trash']);
});

it('reports the cache, orphaned metadata and shadowed keys', function (): void {
    app(Factory::class)->add('tabler', ['path' => $this->workspace->svgSet('tabler', ['home']), 'prefix' => 'tabler']);
    $this->workspace->metadata(['tabler.gone' => ['label' => 'Weg']]);

    $report = app(IconRegistry::class)->validate();

    expect($report)->toMatchArray([
        'icon_count' => 2,
        'cache_exists' => false,
        'cache_is_fresh' => false,
        'orphaned_metadata' => ['tabler.gone'],
        'shadowed_icons' => ['tabler.home'],
    ]);
});

it('keeps the first configured provider when two list the same key', function (): void {
    app(Factory::class)->add('tabler', ['path' => $this->workspace->svgSet('tabler', ['home']), 'prefix' => 'tabler']);

    expect(app(IconRegistry::class)->find('tabler.home')?->provider)->toBe('flux');
});

it('skips a provider whose package is not installed', function (): void {
    config()->set('icon-registry.providers', [
        'missing' => ['driver' => FakeIconProvider::class, 'available' => false],
        'present' => ['driver' => FakeIconProvider::class],
    ]);

    expect(array_keys(app(IconRegistry::class)->providers()))->toBe(['present'])
        ->and(app(IconRegistry::class)->all()->pluck('key')->all())->toBe(['fake.star']);
});

it('draws a key with the provider that listed it', function (): void {
    config()->set('icon-registry.providers', ['fake' => ['driver' => FakeIconProvider::class]]);

    expect(app(IconRegistry::class)->render('fake.star')->toHtml())->toBe('<i data-fake="star"></i>');
});

it('refuses to draw a key no provider lists', function (): void {
    app(IconRegistry::class)->render('tabler.nope');
})->throws(UnknownIcon::class, 'tabler.nope');

it('refuses a driver that is not an icon provider', function (): void {
    config()->set('icon-registry.providers', ['wrong' => ['driver' => stdClass::class]]);

    app(IconRegistry::class)->providers();
})->throws(InvalidArgumentException::class, 'must implement');
