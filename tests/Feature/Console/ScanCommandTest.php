<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Actions\ScanIconUsages;
use Ympact\IconRegistry\Console\ScanCommand;
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
        <x-registry-icon icon="tabler.home" />
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

it('does not give the items of a compact PHP menu each other\'s labels', function (): void {
    $report = ($this->scan)('Nav.php', <<<'PHP'
        <?php
        return [
            ['label' => 'Start', 'icon' => 'tabler.home'],
            ['label' => 'Remove', 'icon' => 'tabler.trash'],
        ];
        PHP);

    expect($report->icons()['tabler.home']['usages'][0]->contextKeywords)->toBe(['start'])
        ->and($report->icons()['tabler.trash']['usages'][0]->contextKeywords)->toBe(['remove']);
});

it('keeps a label to its own array and ignores labels nested deeper', function (): void {
    $report = ($this->scan)('Nav.php', <<<'PHP'
        <?php
        return [
            [
                'icon' => 'tabler.home',
                'children' => [['label' => 'Nested']],
                'label' => 'Start',
            ],
            [
                'label' => 'Remove',
                'icon' => 'tabler.trash',
            ],
        ];
        PHP);

    expect($report->icons()['tabler.home']['usages'][0]->contextKeywords)->toBe(['start'])
        ->and($report->icons()['tabler.trash']['usages'][0]->contextKeywords)->toBe(['remove']);
});

it('keeps a label to its own fluent chain', function (): void {
    $report = ($this->scan)('Actions.php', <<<'PHP'
        <?php
        return [
            Action::make('a')->label('Start')->icon('tabler.home'),
            Action::make('b')
                ->icon('tabler.trash')
                ->label('Remove'),
        ];
        PHP);

    expect($report->icons()['tabler.home']['usages'][0]->contextKeywords)->toBe(['start'])
        ->and($report->icons()['tabler.trash']['usages'][0]->contextKeywords)->toBe(['remove']);
});

it('counts a tag spread over several lines once, at its opening line', function (): void {
    $report = ($this->scan)('page.blade.php', "<flux:button\n  icon=\"tabler.home\"\n  tooltip=\"Start\"\n/>");

    expect($report->icons()['tabler.home']['usages'])->toHaveCount(1)
        ->and($report->icons()['tabler.home']['usages'][0]->line)->toBe(1);
});

it('still finds an icon in a PHP array bound on a tag that uses another icon', function (): void {
    $report = ($this->scan)('page.blade.php', "<flux:button icon=\"tabler.home\"\n  :items=\"[['icon' => 'tabler.trash']]\" />");

    expect(array_keys($report->icons()))->toBe(['tabler.home', 'tabler.trash']);
});

it('counts every reference on a line and keeps the keywords of each', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button icon="star">Like</flux:button><flux:button icon="star">Favourite</flux:button>');

    expect($report->icons()['app.star']['usages'])->toHaveCount(2)
        ->and($report->icons()['app.star']['keywords'])->toContain('like', 'favourite');
});

it('does not take keywords from the code in directives and action arguments', function (): void {
    $report = ($this->scan)('page.blade.php', <<<'BLADE'
        <flux:button icon="star" wire:click="remove($user->profile)">
            @if (in_array($user->isAdmin, ['staff']))
                Save
            @endif
        </flux:button>
        BLADE);

    expect($report->icons()['app.star']['usages'][0]->contextKeywords)->toEqualCanonicalizing(['remove', 'save']);
});

it('does not take route, config, view or translation names for stored keys', function (): void {
    $report = ($this->scan)('Nav.php', <<<'PHP'
        <?php
        route('tabler.home');
        config('tabler.home');
        view('tabler.home');
        __('tabler.home');
        Route::get('/')->name('tabler.home');
        PHP);

    expect($report->icons())->toBe([]);
});

it('still finds a stored key passed to something that is not a named lookup', function (): void {
    $report = ($this->scan)('Nav.php', "<?php\n\$item->update(['glyph' => 'tabler.home']);\nremember('tabler.trash');\n");

    expect(array_keys($report->icons()))->toBe(['tabler.home', 'tabler.trash']);
});

it('does not report the first half of a concatenated name as an unknown icon', function (): void {
    $report = ($this->scan)('a.php', "<?php\nsvg('heroicon-'.\$name);\nsvg('missing', 'size-5');\n");

    expect(array_map(fn (IconUsage $usage) => $usage->reference, $report->unknown()))->toBe(['missing']);
});

it('does not list a bound constant that means no icon as dynamic', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button :icon="null" :icon-trailing="false" :icon:leading="\'\'" />');

    expect($report->dynamic())->toBe([]);
});

it('never writes a word that only comes from the file path, however often it repeats', function (): void {
    ($this->scan)('users/users/users.blade.php', '<flux:button icon="star">Favourite</flux:button>');

    artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

    expect((require $this->workspace->metadataPath)['app.star']['use_cases'])->toBe(['favourite']);
});

it('adds a new entry after its vendor and keeps the section banner and order in place', function (): void {
    File::ensureDirectoryExists(dirname($this->workspace->metadataPath));
    File::put($this->workspace->metadataPath, <<<'PHP'
        <?php

        return [

            // ------
            // tabler section
            // ------

            'tabler.trash' => [
                'label' => 'Weg',
            ],

            'tabler.chart' => [
                'label' => 'Grafiek',
            ],

        ];
        PHP);

    ($this->scan)('page.blade.php', '<flux:button icon="tabler.home">Start</flux:button><flux:button icon="star">Favourite</flux:button>');

    artisan('icon-registry:scan', ['--path' => [$this->source], '--write' => true, '--force' => true])->assertSuccessful();

    $written = File::get($this->workspace->metadataPath);

    expect(array_keys(require $this->workspace->metadataPath))->toBe(['app.star', 'tabler.trash', 'tabler.chart', 'tabler.home'])
        ->and(strpos($written, "'app.star'"))->toBeLessThan(strpos($written, 'tabler section'))
        ->and(strpos($written, 'tabler section'))->toBeLessThan(strpos($written, "'tabler.trash'"));
});

it('recognises the Blade tag the application configured', function (): void {
    config()->set('icon-registry.component', 'my-icon');

    $report = ($this->scan)('page.blade.php', '<x-my-icon icon="missing" /><x-registry-icon icon="also-missing" />');

    expect(array_map(fn (IconUsage $usage) => $usage->reference, $report->unknown()))->toBe(['missing']);
});

it('does not give a component that wraps other icons the text of its children', function (): void {
    $report = ($this->scan)('page.blade.php', <<<'BLADE'
        <flux:navlist.group icon="star" heading="Settings">
            <flux:navlist.item icon="tabler.home">Dashboard</flux:navlist.item>
        </flux:navlist.group>
        BLADE);

    expect($report->icons()['app.star']['usages'][0]->contextKeywords)->toBe(['settings'])
        ->and($report->icons()['tabler.home']['usages'][0]->contextKeywords)->toBe(['dashboard']);
});

it('reads the text of a nested tag that draws no icon, without the code in its attributes', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button icon="star"><flux:badge :color="$status->color" :map="[\'a\' => $tint]">Save</flux:badge></flux:button>');

    expect($report->icons()['app.star']['usages'][0]->contextKeywords)->toBe(['save']);
});

it('keeps the slot text after a comparison in a directive', function (): void {
    $report = ($this->scan)('page.blade.php', '<flux:button icon="star">@if ($count < 1) Save <b>now</b> @endif</flux:button>');

    expect($report->icons()['app.star']['usages'][0]->contextKeywords)->toEqualCanonicalizing(['save', 'now']);
});

it('reads the text of an @lang directive like a translated echo', function (): void {
    $report = ($this->scan)('page.blade.php', "<flux:button icon=\"star\">@lang('Delete')</flux:button>");

    expect($report->icons()['app.star']['usages'][0]->contextKeywords)->toBe(['delete']);
});

it('does not take an instance lookup for a stored key', function (): void {
    $report = ($this->scan)('Nav.php', "<?php\nconfig()->get('tabler.home');\n");

    expect($report->icons())->toBe([]);
});

it('resolves an icon with a numeric name', function (): void {
    $this->workspace->flux(['' => ['404']]);

    $report = ($this->scan)('page.blade.php', '<flux:icon.404 />');

    expect(array_keys($report->icons()))->toBe(['app.404'])
        ->and($report->unknown())->toBe([]);
});

it('credits an overridden Flux icon to the application, unless the key is written out', function (): void {
    config()->set('icon-registry.providers.flux.flux_icons', true);
    $this->workspace->flux(['' => ['home']]);

    $report = ($this->scan)('page.blade.php', "<flux:icon.home />\n<x-registry-icon icon=\"flux.home\" />");

    expect(array_keys($report->icons()))->toBe(['app.home', 'flux.home'])
        ->and($report->icons()['app.home']['usages'][0]->line)->toBe(1);
});

it('counts a file once when scanned directories overlap', function (): void {
    ($this->scan)('admin/page.blade.php', '<flux:icon.star />');

    $report = app(ScanIconUsages::class)->handle([$this->source, $this->source.'/admin']);

    expect($report->icons()['app.star']['usages'])->toHaveCount(1);
});

it('treats a Windows drive path as absolute and resolves a relative one from the application root', function (): void {
    // Read directly: the console's error output strips the base path, so it cannot show the difference.
    $command = new class extends ScanCommand
    {
        public function resolve(string $path): string
        {
            return $this->absolute($path);
        }
    };

    expect($command->resolve('C:\\modules'))->toBe('C:\\modules')
        ->and($command->resolve('D:/modules'))->toBe('D:/modules')
        ->and($command->resolve('/srv/modules'))->toBe('/srv/modules')
        ->and($command->resolve('modules'))->toBe(base_path('modules'));
});
