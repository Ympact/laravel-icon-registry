<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
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
