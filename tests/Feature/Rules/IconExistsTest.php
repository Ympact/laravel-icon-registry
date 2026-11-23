<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Tests\Support\FakeIconProvider;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\Rules\IconExists;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux(['tabler' => ['trash', 'home'], 'acme' => ['logo']])
        ->metadata(['tabler.trash' => ['label' => 'Remove', 'aliases' => ['delete']]]);

    $this->errorFor = fn (mixed $value, ?IconExists $rule = null): string => Validator::make(
        ['icon' => $value],
        ['icon' => ['nullable', $rule ?? new IconExists]],
    )->errors()->first('icon');
});

afterEach(fn () => $this->workspace->delete());

it('passes for an icon that exists', function (): void {
    expect(($this->errorFor)('tabler.trash'))->toBe('');
});

it('fails for an icon with no file behind it', function (): void {
    expect(($this->errorFor)('tabler.does-not-exist'))->toStartWith('The selected icon does not exist.');
});

it('suggests the closest icon when the name is a typo', function (): void {
    expect(($this->errorFor)('tabler.trahs'))->toContain('tabler.trash');
});

it('says so plainly when nothing comes close', function (): void {
    expect(($this->errorFor)('zzzzzzzz.qqqqqqqq'))->toBe('The selected icon does not exist.');
});

it('leaves emptiness to nullable and required', function (): void {
    expect(($this->errorFor)(null))->toBe('')
        ->and(($this->errorFor)(''))->toBe('')
        ->and(($this->errorFor)('   '))->toBe('');
});

it('can be scoped to a single vendor', function (): void {
    expect(($this->errorFor)('tabler.trash', IconExists::vendor('tabler')))->toBe('')
        ->and(($this->errorFor)('acme.logo', IconExists::vendor('tabler')))->not->toBe('');
});

it('suggests an icon of the same name from the allowed vendor for a key of another vendor', function (): void {
    $this->workspace->flux(['tabler' => ['logo']]);

    expect(($this->errorFor)('acme.logo', IconExists::vendor('tabler')))
        ->toBe('The selected icon does not exist. Did you mean tabler.logo?');
});

it('can be scoped to a single provider', function (): void {
    app(Factory::class)->add('heroicons', ['path' => $this->workspace->svgSet('heroicons', ['o-home']), 'prefix' => 'heroicon']);

    expect(($this->errorFor)('heroicons.o-home', IconExists::provider('blade-icons')))->toBe('')
        ->and(($this->errorFor)('tabler.home', IconExists::provider('blade-icons')))->not->toBe('');
});

it('accepts an icon built after the cache was written', function (): void {
    // A cache that predates the icon would reject a valid key without the re-check on disk.
    app(IconRegistry::class)->rebuildCache();

    File::put("{$this->workspace->fluxPath}/tabler/sparkles.blade.php", '<svg/>');

    expect(($this->errorFor)('tabler.sparkles'))->toBe('');
});

it('translates its message', function (): void {
    app()->setLocale('nl');

    expect(($this->errorFor)('tabler.trahs'))->toBe('Het geselecteerde icon bestaat niet. Bedoelde je tabler.trash?');
});

it('rejects a value that is not a string', function (): void {
    expect(($this->errorFor)(['tabler.trash']))->toBe('The selected icon does not exist.')
        ->and(($this->errorFor)(5))->toBe('The selected icon does not exist.');
});

it('rejects a key with surrounding whitespace, which could not be drawn once stored', function (): void {
    expect(($this->errorFor)(' tabler.trash '))->toContain('tabler.trash');
});

it('accepts a hidden icon, but never suggests one', function (): void {
    $this->workspace->metadata(['tabler.trash' => ['hidden' => true]]);

    expect(($this->errorFor)('tabler.trash'))->toBe('')
        ->and(($this->errorFor)('tabler.trahs'))->not->toContain('tabler.trash');
});

describe('drawable by key', function (): void {
    beforeEach(function (): void {
        // Loose files are listed as "app.<name>" but drawn by their bare name.
        $this->workspace->flux(['' => ['home', 'logo']]);
    });

    it('accepts an icon whose key is what its provider draws', function (): void {
        expect(($this->errorFor)('tabler.trash', IconExists::drawableByKey()))->toBe('');
    });

    it('rejects a listed icon whose provider draws it by another name', function (): void {
        expect(($this->errorFor)('app.logo'))->toBe('')
            ->and(($this->errorFor)('app.logo', IconExists::drawableByKey()))->toStartWith('The selected icon does not exist.');
    });

    it('rejects a Blade Icons key, which is drawn by its prefixed name', function (): void {
        app(Factory::class)->add('heroicons', ['path' => $this->workspace->svgSet('heroicons', ['o-home']), 'prefix' => 'heroicon']);

        expect(($this->errorFor)('heroicons.o-home', IconExists::drawableByKey()))->not->toBe('');
    });

    it('suggests only an icon that is drawable by its key', function (): void {
        // Without the constraint "app.home" ranks first: it ties with "tabler.home" and sorts before it.
        expect(($this->errorFor)('app.home', IconExists::drawableByKey()))
            ->toBe('The selected icon does not exist. Did you mean tabler.home?');
    });

    it('accepts a custom provider whose reference is the key, unless narrowed to Flux', function (): void {
        config()->set('icon-registry.providers.fake', ['driver' => FakeIconProvider::class, 'reference' => 'fake.star']);

        expect(($this->errorFor)('fake.star', IconExists::drawableByKey()))->toBe('')
            ->and(($this->errorFor)('fake.star', new IconExists(provider: 'flux', drawableByKey: true)))->not->toBe('')
            ->and(($this->errorFor)('tabler.trash', new IconExists(provider: 'flux', drawableByKey: true)))->toBe('');
    });

    it('combines with a vendor constraint', function (): void {
        $rule = new IconExists(vendor: 'acme', drawableByKey: true);

        expect(($this->errorFor)('acme.logo', $rule))->toBe('')
            ->and(($this->errorFor)('tabler.trash', $rule))->not->toBe('')
            ->and(($this->errorFor)('app.logo', $rule))->toContain('acme.logo');
    });
});
