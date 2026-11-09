<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Actions\ScanIconUsages;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconUsage;
use Ympact\IconRegistry\IconUsageReport;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux(['' => ['star', 'chevron-up', 'chevron-down'], 'tabler' => ['home', 'trash']]);
    $this->source = $this->workspace->root.'/source';

    app(Factory::class)->add('heroicons', ['path' => $this->workspace->svgSet('heroicons', ['o-bell']), 'prefix' => 'heroicon']);

    $this->scan = function (string $file, string $contents): IconUsageReport {
        File::ensureDirectoryExists(dirname("{$this->source}/{$file}"));
        File::put("{$this->source}/{$file}", $contents);

        return app(ScanIconUsages::class)->handle([$this->source]);
    };
});

afterEach(fn () => $this->workspace->delete());

it('resolves every way a Blade template names an icon to its registry key', function (): void {
    $report = ($this->scan)('page.blade.php', <<<'BLADE'
        <flux:icon.star />
        <flux:icon name="tabler.home" />
        <flux:button icon="tabler.trash" />
        <flux:button icon:trailing="chevron-up" />
        <x-heroicon-o-bell />
        <x-icon-registry::icon icon="tabler.home" />
        BLADE);

    expect(array_keys($report->icons()))->toBe(['app.chevron-up', 'app.star', 'heroicons.o-bell', 'tabler.home', 'tabler.trash'])
        ->and($report->icons()['tabler.home']['usages'])->toHaveCount(2)
        ->and($report->unknown())->toBe([])
        ->and($report->dynamic())->toBe([]);
});

it('finds icons in PHP arrays, fluent calls, svg helpers and stored keys', function (): void {
    $report = ($this->scan)('Menu.php', <<<'PHP'
        <?php
        $items = [['icon' => 'star']];
        $action->icon('heroicon-o-bell');
        svg('tabler.home');
        return 'tabler.trash';
        PHP);

    expect(array_keys($report->icons()))->toBe(['app.star', 'heroicons.o-bell', 'tabler.home', 'tabler.trash']);
});

it('takes keywords from labels, slot text, actions and links, without the icon name itself', function (): void {
    $report = ($this->scan)('admin/users.blade.php', <<<'BLADE'
        <flux:button icon="tabler.trash" wire:click="removeAccount({{ $user->id }})" tooltip="Permanently">
            {{ __('Delete member') }}
        </flux:button>
        <flux:navlist.item icon="tabler.home" :href="route('dashboard.overview')">Trash home</flux:navlist.item>
        BLADE);

    $icons = $report->icons();

    expect($icons['tabler.trash']['keywords'])->toBe(['account', 'delete', 'member', 'permanently', 'remove', 'admin', 'users'])
        ->and($icons['tabler.home']['keywords'])->toBe(['dashboard', 'overview', 'trash', 'admin', 'users']);
});

it('reads the text after a self-closing icon and the label near a PHP reference', function (): void {
    $blade = ($this->scan)('a.blade.php', '<a><flux:icon.star /> Favourite</a>');

    expect($blade->icons()['app.star']['keywords'])->toContain('favourite');

    File::delete("{$this->source}/a.blade.php");

    $php = ($this->scan)('Nav.php', <<<'PHP'
        <?php
        return [
            'label' => __('Start page'),
            'icon' => 'tabler.home',
        ];
        PHP);

    expect($php->icons()['tabler.home']['keywords'])->toBe(['start', 'nav']);
});

it('does not take keywords from the line after a self-closing icon', function (): void {
    $report = ($this->scan)('a.blade.php', "<flux:icon.star />\nUnrelated");

    expect($report->icons()['app.star']['keywords'])->not->toContain('unrelated');
});

it('keeps an acronym whole and splits camelCase in an action name', function (): void {
    $report = ($this->scan)('a.blade.php', '<flux:button icon="star" wire:click="openHelp" tooltip="FAQ" />');

    expect($report->icons()['app.star']['keywords'])->toContain('faq', 'open', 'help');
});

it('keeps the line number of a reference below a multi-line comment', function (): void {
    $report = ($this->scan)('page.blade.php', "{{-- <flux:icon.tabler.trash />\n still a comment --}}\n<flux:icon.star />");

    expect(array_keys($report->icons()))->toBe(['app.star'])
        ->and($report->icons()['app.star']['usages'][0])->toBeInstanceOf(IconUsage::class)
        ->and($report->icons()['app.star']['usages'][0]->line)->toBe(3);
});

it('ignores icons in PHP comments', function (): void {
    $report = ($this->scan)('Menu.php', "<?php\n// \$items = ['icon' => 'star'];\n/* 'tabler.home' */\n");

    expect($report->icons())->toBe([]);
});

it('reports a literal no provider lists only where the tag can only mean an icon', function (): void {
    $report = ($this->scan)('page.blade.php', <<<'BLADE'
        <flux:icon.missing />
        <flux:button icon="also-missing" />
        <x-card icon="not-necessarily-an-icon" />
        <x-alert />
        BLADE);

    expect(array_map(fn (IconUsage $usage) => $usage->reference, $report->unknown()))->toBe(['missing', 'also-missing']);
});

it('resolves the literals of a conditional and reports other expressions as dynamic', function (): void {
    $report = ($this->scan)('page.blade.php', <<<'BLADE'
        <flux:button :icon="$open ? 'chevron-up' : 'chevron-down'" />
        <flux:icon :name="$item->icon" />
        <flux:icon name="{{ $item->icon }}" />
        BLADE);

    expect(array_keys($report->icons()))->toBe(['app.chevron-down', 'app.chevron-up'])
        ->and(array_map(fn (IconUsage $usage) => $usage->reference, $report->dynamic()))->toBe(['$item->icon', '{{ $item->icon }}']);
});

it('reads a tag whose attributes contain an arrow', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button {{ $attributes->merge() }} icon="star" @class(["a" => $b]) />');

    expect(array_keys($report->icons()))->toBe(['app.star']);
});

it('does not report one reference twice when a PHP pattern sees the same tag', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button icon="tabler.home" tooltip="Dashboard" />');

    expect($report->icons()['tabler.home']['usages'])->toHaveCount(1)
        ->and($report->icons()['tabler.home']['keywords'])->toContain('dashboard');
});

it('prints a table, the unknown references and the dynamic ones', function (): void {
    ($this->scan)('page.blade.php', "<flux:icon.star />\n<flux:icon.missing />\n<flux:icon :name=\"\$dynamic\" />");

    artisan('icon-registry:scan', ['--path' => [$this->source]])
        ->expectsOutputToContain('app.star')
        ->expectsOutputToContain('Not listed by any provider:')
        ->expectsOutputToContain('missing')
        ->expectsOutputToContain('Chosen at runtime, so not resolved:')
        ->assertSuccessful();
});

it('prints JSON that scripts can read', function (): void {
    ($this->scan)('page.blade.php', '<flux:button icon="star">Favourite</flux:button>');

    expect(Artisan::call('icon-registry:scan', ['--path' => [$this->source], '--json' => true]))->toBe(0);

    $report = json_decode(Artisan::output(), true);

    expect($report['icons']['app.star']['keywords'])->toBe(['favourite'])
        ->and($report['icons']['app.star']['usages'][0])->toMatchArray(['reference' => 'star', 'line' => 1])
        ->and($report['unknown'])->toBe([])
        ->and($report['dynamic'])->toBe([]);
});

it('fails on a path that is not a directory', function (): void {
    artisan('icon-registry:scan', ['--path' => [$this->source.'/nope']])
        ->expectsOutputToContain('Not a directory')
        ->assertFailed();
});

describe('--write', function (): void {
    beforeEach(function (): void {
        $this->writeCommand = fn () => artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true]);
    });

    it('adds context keywords as use cases once confirmed, and not words from the file path', function (): void {
        ($this->scan)('admin/page.blade.php', '<flux:button icon="star">Favourite</flux:button>');

        ($this->writeCommand)()
            ->expectsConfirmation("Add these to {$this->workspace->metadataPath}?", 'yes')
            ->expectsOutputToContain('Added use cases to 1 icons.')
            ->assertSuccessful();

        expect(require $this->workspace->metadataPath)->toBe(['app.star' => ['use_cases' => ['favourite']]]);
    });

    it('leaves the file alone when the preview is declined', function (): void {
        ($this->scan)('page.blade.php', '<flux:button icon="star">Favourite</flux:button>');

        ($this->writeCommand)()
            ->expectsConfirmation("Add these to {$this->workspace->metadataPath}?", 'no')
            ->expectsOutputToContain('Nothing was written.')
            ->assertSuccessful();

        expect($this->workspace->metadataPath)->not->toBeFile();
    });

    it('writes without asking with --force', function (): void {
        ($this->scan)('page.blade.php', '<flux:button icon="star">Favourite</flux:button>');

        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

        expect(require $this->workspace->metadataPath)->toHaveKey('app.star');
    });

    it('only appends, keeping comments, labels, aliases and custom fields', function (): void {
        File::ensureDirectoryExists(dirname($this->workspace->metadataPath));
        File::put($this->workspace->metadataPath, <<<'PHP'
            <?php

            return [

                // Curated by design.
                'app.star' => [
                    'label' => 'Ster',
                    'aliases' => ['rating'],
                    'use_cases' => ['action.highlight'],
                    'category' => 'marketing',
                ],

            ];
            PHP);

        ($this->scan)('page.blade.php', '<flux:button icon="star" tooltip="Rating highlight">Favourite</flux:button>');

        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

        expect(require $this->workspace->metadataPath)->toBe(['app.star' => [
            'label' => 'Ster',
            'aliases' => ['rating'],
            'use_cases' => ['action.highlight', 'favourite'],
            'category' => 'marketing',
        ]])->and(File::get($this->workspace->metadataPath))->toContain('// Curated by design.');
    });

    it('adds nothing on a second run, even with more keywords than one run takes', function (): void {
        ($this->scan)('page.blade.php', '<flux:button icon="star" tooltip="alpha bravo charlie delta echo foxtrot golf" />');

        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

        $written = require $this->workspace->metadataPath;

        expect($written['app.star']['use_cases'])->toHaveCount(5);

        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])
            ->expectsOutputToContain('No new use cases to add.')
            ->assertSuccessful();
    });

    it('makes the new use cases searchable by rebuilding an existing cache', function (): void {
        app(IconRegistry::class)->rebuildCache();
        ($this->scan)('page.blade.php', '<flux:button icon="star">Favourite</flux:button>');

        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

        expect(app(IconRegistry::class)->search('favourite')->pluck('key')->all())->toBe(['app.star']);
    });

    it('refuses to combine with --json', function (): void {
        artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--json' => true])
            ->expectsOutputToContain('cannot be combined')
            ->assertFailed();
    });
});
