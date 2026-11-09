<?php

use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

/**
 * Search is scored, not filtered: every icon matching every query token comes back, ordered by
 * how well it matched. Typo tolerance is a fallback tier.
 */
beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux([
            'tabler' => ['trash', 'file-x', 'layers-subtract', 'home', 'device-floppy'],
            'heroicons' => ['trash', 'home'],
        ])
        ->metadata([
            'tabler.trash' => [
                'label' => 'Verwijderen',
                'aliases' => ['delete', 'prullenbak'],
            ],
            'heroicons.trash' => [
                'label' => 'Verwijderen',
                'aliases' => ['delete'],
            ],
            // Only mentions the word inside a longer phrase, so it must rank below a label.
            'tabler.layers-subtract' => [
                'label' => 'Aftrekken',
                'aliases' => ['verwijderen uit', 'subtract'],
            ],
            'tabler.file-x' => [
                'label' => 'Bestand verwijderen',
                'aliases' => ['remove file'],
            ],
            'tabler.home' => [
                'label' => 'Woonfunctie',
                'aliases' => ['huis', 'woning'],
            ],
            'tabler.device-floppy' => [
                'label' => 'Opslaan',
                'aliases' => ['save', 'bewaren'],
            ],
        ])
        ->purposes([
            'action.delete' => ['icon' => 'tabler.trash', 'label' => 'Delete'],
            'geodata.gebruiksdoel.woonfunctie' => ['icon' => 'tabler.home', 'label' => 'Woonfunctie'],
        ]);

    $this->keys = fn (string $query, ?string $vendor = null): array => app(IconRegistry::class)
        ->search($query, $vendor)
        ->pluck('key')
        ->all();
});

afterEach(fn () => $this->workspace->delete());

it('ranks a label match above a mention inside an alias phrase', function (): void {
    $keys = ($this->keys)('verwijderen');

    expect(array_slice($keys, 0, 2))->toEqualCanonicalizing(['heroicons.trash', 'tabler.trash'])
        ->and($keys)->toContain('tabler.layers-subtract')
        ->and(array_search('tabler.layers-subtract', $keys, true))->toBeGreaterThan(1);
});

it('requires every token to match, in any order', function (): void {
    expect(($this->keys)('bestand verwijderen'))->toBe(['tabler.file-x'])
        ->and(($this->keys)('verwijderen bestand'))->toBe(['tabler.file-x'])
        ->and(($this->keys)('opslaan verwijderen'))->toBe([]);
});

it('searches aliases and purposes, not just the label', function (): void {
    expect(($this->keys)('prullenbak'))->toBe(['tabler.trash'])
        ->and(($this->keys)('woonfunctie'))->toContain('tabler.home')
        ->and(($this->keys)('gebruiksdoel'))->toBe(['tabler.home']);
});

it('lets the icon name and vendor scope a search', function (): void {
    expect(($this->keys)('tabler home'))->toBe(['tabler.home'])
        ->and(($this->keys)('trash', 'heroicons'))->toBe(['heroicons.trash']);
});

it('tolerates a typo', function (): void {
    expect(($this->keys)('verwijdren'))->toContain('tabler.trash')
        ->and(($this->keys)('prullenbk'))->toBe(['tabler.trash']);
});

it('counts a swapped pair of letters as a single mistake', function (): void {
    expect(($this->keys)('tabler.trahs'))->toBe(['tabler.trash'])
        ->and(($this->keys)('prullenbka'))->toBe(['tabler.trash']);
});

it('only falls back to typo matching when nothing matched outright', function (): void {
    expect(($this->keys)('home'))->toEqualCanonicalizing(['heroicons.home', 'tabler.home']);
});

it('does not fuzzy-match tokens too short to be meaningful', function (): void {
    expect(($this->keys)('xy'))->toBe([]);
});

it('returns everything in label order when the query is empty', function (): void {
    expect(($this->keys)(''))->toHaveCount(7)
        ->and(array_slice(($this->keys)(''), 0, 2))->toBe(['heroicons.home', 'heroicons.trash']);
});

it('limits the number of results', function (): void {
    expect(app(IconRegistry::class)->search('', limit: 3))->toHaveCount(3);
});
