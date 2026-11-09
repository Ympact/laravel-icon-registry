<?php

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\Blade;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\Facades\Icons;

beforeEach(function (): void {
    $this->workspace = new IconWorkspace;

    config()->set('icon-registry.providers.flux.flux_icons', true);

    app(Factory::class)->add('heroicons', [
        'path' => $this->workspace->svgSet('heroicons', ['o-home']),
        'prefix' => 'heroicon',
    ]);
});

afterEach(fn () => $this->workspace->delete());

/** Flux's real icons hardcode aria-hidden, which is what these cases must survive. */
dataset('providers', ['flux' => 'flux.home', 'blade icons' => 'heroicons.o-home']);

function openingTag(string $html): string
{
    preg_match('/<svg\b[^>]*>/', $html, $match);

    return $match[0];
}

it('treats an icon without a name as decorative', function (string $key): void {
    $tag = openingTag(Blade::render('<x-icon-registry::icon :icon="$key" />', ['key' => $key]));

    expect(substr_count($tag, 'aria-hidden='))->toBe(1)
        ->and($tag)->toContain('aria-hidden="true"')
        ->not->toContain('role=');
})->with('providers');

it('names an icon given a label, without hiding it', function (string $key): void {
    $tag = openingTag(Blade::render('<x-icon-registry::icon :icon="$key" label="Home" />', ['key' => $key]));

    expect($tag)->toContain('role="img"')
        ->toContain('aria-label="Home"')
        ->not->toContain('aria-hidden')
        ->not->toContain(' label=');
})->with('providers');

it('accepts a label through the facade', function (string $key): void {
    $tag = openingTag(Icons::render($key, ['label' => 'Home'])->toHtml());

    expect($tag)->toContain('role="img"')->toContain('aria-label="Home"')->not->toContain('aria-hidden');
})->with('providers');

it('treats an empty label as decorative', function (string $key, ?string $label): void {
    $tag = openingTag(Icons::render($key, ['label' => $label])->toHtml());

    expect($tag)->toContain('aria-hidden="true"')->not->toContain('aria-label')->not->toContain(' label=');
})->with('providers')->with(['null' => null, 'empty' => '', 'blank' => '   ']);

it('lets a label replace an explicit aria-hidden', function (string $key): void {
    $tag = openingTag(Icons::render($key, ['label' => 'Home', 'aria-hidden' => 'true'])->toHtml());

    expect($tag)->toContain('aria-label="Home"')->not->toContain('aria-hidden');
})->with('providers');

it('adds the image role to a name passed directly', function (string $key, string $attribute): void {
    $tag = openingTag(Icons::render($key, [$attribute => 'name'])->toHtml());

    expect($tag)->toContain('role="img"')->toContain("{$attribute}=\"name\"")->not->toContain('aria-hidden');
})->with('providers')->with(['aria-label', 'aria-labelledby']);

it('does not hide an icon with a title', function (string $key): void {
    $tag = openingTag(Icons::render($key, ['title' => 'Home'])->toHtml());

    expect($tag)->not->toContain('aria-hidden');
})->with('providers');

it('keeps an explicit role or aria-hidden', function (string $key): void {
    $role = openingTag(Icons::render($key, ['role' => 'presentation'])->toHtml());
    $visible = openingTag(Icons::render($key, ['aria-hidden' => 'false'])->toHtml());

    expect($role)->toContain('role="presentation"')->not->toContain('aria-hidden')
        ->and($visible)->toContain('aria-hidden="false"')
        ->and(substr_count($visible, 'aria-hidden='))->toBe(1);
})->with('providers');

it('writes a boolean aria-hidden as a valid ARIA value', function (string $key): void {
    expect(openingTag(Icons::render($key, ['aria-hidden' => true])->toHtml()))->toContain('aria-hidden="true"');
})->with('providers');

it('escapes a dynamic label', function (string $key): void {
    $html = Icons::render($key, ['label' => '"><script>alert(1)</script>'])->toHtml();

    expect($html)->not->toContain('<script>')
        ->and(openingTag($html))->toContain('aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"');
})->with('providers');

it('escapes a dynamic label from the component exactly once', function (string $key): void {
    $html = Blade::render('<x-icon-registry::icon :icon="$key" :label="$label" />', ['key' => $key, 'label' => 'Save & "close"']);

    expect(openingTag($html))->toContain('aria-label="Save &amp; &quot;close&quot;"');
})->with('providers');
