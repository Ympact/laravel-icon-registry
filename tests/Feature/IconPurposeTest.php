<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\ViewException;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Exceptions\UnknownIcon;
use Ympact\IconRegistry\Exceptions\UnknownPurpose;
use Ympact\IconRegistry\Facades\Icons;
use Ympact\IconRegistry\IconRegistry;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux(['tabler' => ['trash', 'home']])
        ->purposes([
            'action.delete' => ['icon' => 'tabler.trash', 'label' => 'Delete'],
            'action.remove' => ['icon' => 'tabler.trash', 'label' => 'Remove'],
        ]);

    // Flux resolves components in a hashed view namespace, where an application's resources/views/flux comes first.
    app('view')->prependNamespace(hash('xxh128', 'flux'), dirname($this->workspace->fluxPath));
});

afterEach(fn () => $this->workspace->delete());

it('resolves a purpose to its icon', function (): void {
    expect(Icons::purpose('action.delete')->key)->toBe('tabler.trash');
});

it('lists the purposes by name', function (): void {
    expect(Icons::purposes()->keys()->all())->toBe(['action.delete', 'action.remove'])
        ->and(Icons::purposes()['action.delete']->label)->toBe('Delete');
});

it('has no purposes without a purposes file', function (): void {
    File::delete($this->workspace->purposesPath);

    expect(Icons::purposes())->toBeEmpty();
});

it('throws for an unknown purpose', function (): void {
    Icons::purpose('action.nope');
})->throws(UnknownPurpose::class, 'action.nope');

it('throws when a purpose points at a missing icon', function (): void {
    $this->workspace->purposes(['action.gone' => ['icon' => 'tabler.gone', 'label' => 'Gone']]);

    Icons::purpose('action.gone');
})->throws(UnknownIcon::class, 'tabler.gone');

dataset('malformed purposes', [
    'no icon' => [['label' => 'Broken']],
    'no label' => [['icon' => 'tabler.trash']],
    'empty label' => [['icon' => 'tabler.trash', 'label' => ' ']],
]);

it('refuses to draw a malformed purpose, naming it', function (array $entry): void {
    $this->workspace->purposes(['action.broken' => $entry]);

    Icons::renderPurpose('action.broken');
})->with('malformed purposes')->throws(UnknownPurpose::class, 'action.broken');

it('keeps every other icon and purpose working beside a malformed purpose', function (array $entry): void {
    $this->workspace->purposes([
        'action.broken' => $entry,
        'action.delete' => ['icon' => 'tabler.trash', 'label' => 'Delete'],
    ]);

    expect(Icons::purposes()->keys()->all())->toBe(['action.delete'])
        ->and(Icons::render('tabler.home')->toHtml())->toContain('data-icon="home"')
        ->and(Icons::renderPurpose('action.delete')->toHtml())->toContain('data-icon="trash"');
})->with('malformed purposes');

it('fails the doctor on a malformed purpose instead of crashing', function (): void {
    $this->workspace->purposes(['action.broken' => ['label' => 'Broken']]);

    artisan('icon-registry:doctor')
        ->expectsOutputToContain('Purposes need both an icon and a label:')
        ->expectsOutputToContain('action.broken')
        ->assertFailed();
});

it('draws a purpose as decorative by default', function (): void {
    $html = Blade::render('<x-registry-icon purpose="action.delete" class="size-5" />');

    expect($html)->toContain('data-icon="trash"')
        ->toContain('class="size-5"')
        ->toContain('aria-hidden="true"')
        ->not->toContain('aria-label')
        ->not->toContain('purpose=');
});

it('names a purpose only when asked', function (): void {
    $html = Blade::render('<x-registry-icon purpose="action.delete" labelled />');

    expect($html)->toContain('role="img"')
        ->toContain('aria-label="Delete"')
        ->not->toContain('aria-hidden')
        ->not->toContain('labelled');
});

it('accepts a labelled purpose through the facade', function (): void {
    expect(Icons::renderPurpose('action.delete', ['labelled' => true])->toHtml())->toContain('aria-label="Delete"');
});

it('translates the label of a purpose', function (): void {
    app('translator')->addLines(['icons.delete' => 'Verwijderen'], 'en', '*');
    $this->workspace->purposes(['action.delete' => ['icon' => 'tabler.trash', 'label' => 'icons.delete']]);

    expect(Icons::renderPurpose('action.delete', ['labelled' => true])->toHtml())->toContain('aria-label="Verwijderen"');
});

it('escapes the label of a purpose once', function (): void {
    $this->workspace->purposes(['action.save' => ['icon' => 'tabler.home', 'label' => 'Save & "close"']]);

    expect(Blade::render('<x-registry-icon purpose="action.save" labelled />'))
        ->toContain('aria-label="Save &amp; &quot;close&quot;"');
});

it('prefers an explicit label over the label of the purpose', function (): void {
    expect(Blade::render('<x-registry-icon purpose="action.delete" labelled label="Delete invoice" />'))
        ->toContain('aria-label="Delete invoice"');
});

it('throws when drawing an unknown purpose', function (): void {
    Blade::render('<x-registry-icon purpose="action.nope" />');
})->throws(ViewException::class, 'action.nope');

it('records the purposes on the icon and searches them', function (): void {
    $icon = Icons::find('tabler.trash');

    expect($icon->purposes)->toBe(['action.delete', 'action.remove'])
        ->and(Icons::find('tabler.home')->purposes)->toBe([])
        ->and(Icons::search('remove')->pluck('key')->all())->toBe(['tabler.trash']);
});

it('reads a changed purpose while the icons are cached', function (): void {
    app(IconRegistry::class)->rebuildCache();
    app()->forgetScopedInstances();

    $this->workspace->purposes(['action.delete' => ['icon' => 'tabler.home', 'label' => 'Delete']]);

    expect(app(IconRegistry::class)->purpose('action.delete')->key)->toBe('tabler.home');
});

it('fails the doctor on a purpose whose icon no longer exists', function (): void {
    $this->workspace->purposes(['action.gone' => ['icon' => 'tabler.gone', 'label' => 'Gone']]);

    artisan('icon-registry:doctor')
        ->expectsOutputToContain('Purposes point at missing icons:')
        ->expectsOutputToContain('action.gone')
        ->assertFailed();
});

it('passes the doctor when every purpose has its icon', function (): void {
    Icons::rebuildCache();

    artisan('icon-registry:doctor')->expectsOutputToContain('Found 2 purposes.')->assertSuccessful();
});

it('does not treat labelled="false" as asking for a label', function (): void {
    expect(Blade::render('<x-registry-icon purpose="action.delete" labelled="false" />'))
        ->toContain('aria-hidden="true"')
        ->not->toContain('aria-label');
});

it('falls back to the label itself when it names a translation group', function (): void {
    app('translator')->addLines(['icons.delete' => 'Verwijderen'], 'en', '*');
    $this->workspace->purposes(['action.delete' => ['icon' => 'tabler.trash', 'label' => 'icons']]);

    expect(Icons::renderPurpose('action.delete', ['labelled' => true])->toHtml())->toContain('aria-label="icons"');
});

it('draws nothing for a purpose that was never chosen', function (?string $purpose): void {
    expect(trim(Blade::render('<x-registry-icon :purpose="$purpose" />', ['purpose' => $purpose])))->toBe('');
})->with([null, '']);
