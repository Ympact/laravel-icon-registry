<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Actions\AddIconUseCases;
use Ympact\IconRegistry\Actions\ScanIconUsages;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconSearch;
use Ympact\IconRegistry\IconUsageReport;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux(['' => ['star'], 'tabler' => ['home', 'u-turn-left']]);
    $this->source = $this->workspace->root.'/source';

    app(Factory::class)->add('heroicons', ['path' => $this->workspace->svgSet('heroicons', ['o-bell']), 'prefix' => 'heroicon']);

    $this->report = function (string $contents): IconUsageReport {
        File::ensureDirectoryExists($this->source);
        File::put("{$this->source}/page.blade.php", $contents);

        return app(ScanIconUsages::class)->handle([$this->source]);
    };

    $this->metadata = function (string $entry): void {
        File::ensureDirectoryExists(dirname($this->workspace->metadataPath));
        File::put($this->workspace->metadataPath, "<?php\n\nreturn [\n\n    'app.star' => {$entry},\n\n];\n");
    };
});

afterEach(fn () => $this->workspace->delete());

it('matches generic icons by name, without a style prefix, or by whole key', function (array $patterns, array $expected): void {
    config()->set('icon-registry.generic_icons', $patterns);

    $report = ($this->report)(<<<'BLADE'
        <flux:button icon="tabler.home">Label</flux:button>
        <x-heroicon-o-bell /> Label
        BLADE);

    expect(array_keys(app(AddIconUseCases::class)->additionsFrom($report)))->toBe($expected);
})->with([
    'no patterns' => [[], ['heroicons.o-bell', 'tabler.home']],
    'name' => [['home'], ['heroicons.o-bell']],
    'wildcard on a name' => [['ho*'], ['heroicons.o-bell']],
    'name without its style prefix' => [['bell'], ['tabler.home']],
    'name with its style prefix' => [['o-bell'], ['tabler.home']],
    'whole key' => [['tabler.home'], ['heroicons.o-bell']],
    'whole key of another vendor' => [['lucide.home'], ['heroicons.o-bell', 'tabler.home']],
    'part of a name' => [['hom'], ['heroicons.o-bell', 'tabler.home']],
]);

it('does not treat a one-letter word in a name as a style prefix', function (): void {
    config()->set('icon-registry.generic_icons', ['turn-left']);

    $report = ($this->report)('<flux:button icon="tabler.u-turn-left">Label</flux:button>');

    expect(app(AddIconUseCases::class)->additionsFrom($report))->toHaveKey('tabler.u-turn-left');
});

it('names the config key when generic_icons holds something other than a string', function (): void {
    config()->set('icon-registry.generic_icons', [null]);

    $report = ($this->report)('<flux:button icon="star">Label</flux:button>');

    expect(fn () => app(AddIconUseCases::class)->additionsFrom($report))
        ->toThrow(InvalidArgumentException::class, 'icon-registry.generic_icons');
});

it('skips an entry whose locked flag is truthy, and adds to one whose flag is false', function (string $flag, bool $added): void {
    ($this->metadata)("['locked' => {$flag}]");

    $additions = app(AddIconUseCases::class)->additionsFrom(($this->report)('<flux:button icon="star">Favourite</flux:button>'));

    expect(isset($additions['app.star']))->toBe($added);
})->with([
    'true' => ['true', false],
    'one' => ['1', false],
    'false' => ['false', true],
]);

it('does not write to an entry locked after the preview, and reports nothing written', function (): void {
    ($this->metadata)("[\n        'locked' => true,\n    ]");
    // A trailing blank line a rewrite would drop, so a needless write shows up.
    File::append($this->workspace->metadataPath, "\n");
    $before = File::get($this->workspace->metadataPath);

    $written = app(AddIconUseCases::class)->handle(['app.star' => ['favourite']]);

    expect($written)->toBe(0)
        ->and(File::get($this->workspace->metadataPath))->toBe($before);
});

it('returns how many entries gained use cases', function (): void {
    expect(app(AddIconUseCases::class)->handle(['app.star' => ['favourite'], 'tabler.home' => ['start']]))->toBe(2);
});

it('still builds with the two-argument constructor', function (): void {
    $action = new AddIconUseCases(app(IconRegistry::class), app(IconSearch::class));

    expect($action->additionsFrom(($this->report)('<flux:button icon="star">Favourite</flux:button>')))->toBe(['app.star' => ['favourite']]);
});
