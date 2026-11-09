<?php

use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;
use Ympact\IconRegistry\IconRegistry;

/**
 * `icon-registry:doctor --fix` rewrites the metadata file in place, so the header and the
 * comments documenting each entry must survive the rewrite.
 */
beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)->flux([
        'tabler' => ['home', 'trash'],
        'acme' => ['labels'],
    ]);
});

afterEach(fn () => $this->workspace->delete());

function writeMetadataFixture(string $path, string $body): void
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, <<<PHP
        <?php

        /*
        | Icon metadata: this header explains the file.
        */

        return [
        {$body}
        ];

        PHP);
}

it('keeps the header and section comments when it rewrites the file', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            // ----------------------------------------------------------
            // tabler.*: our default set
            // ----------------------------------------------------------

            'tabler.home' => [
                'label' => 'Home',
                'aliases' => ['house'],
            ],

            // Used for both the row action and the bulk action.
            'tabler.trash' => [
                'label' => 'Remove',
                'use_cases' => ['action.delete'],
            ],
        PHP);

    app(IconRegistry::class)->cleanMetadata(write: true);

    $rewritten = File::get($this->workspace->metadataPath);

    expect($rewritten)
        ->toContain('| Icon metadata: this header explains the file.')
        ->toContain('// tabler.*: our default set')
        ->toContain('// Used for both the row action and the bulk action.');

    expect(require $this->workspace->metadataPath)
        ->toHaveKeys(['tabler.home', 'tabler.trash']);
});

it('is a fixed point: rewriting twice changes nothing', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            // ----------------------------------------------------------
            // tabler.*: our default set
            // ----------------------------------------------------------

            'tabler.home' => [
                'label' => 'Home',
                'aliases' => ['house', 'start'],
                'use_cases' => ['navigation.home'],
            ],
        PHP);

    $registry = app(IconRegistry::class);

    $registry->cleanMetadata(write: true);
    $first = File::get($this->workspace->metadataPath);

    $registry->cleanMetadata(write: true);

    expect(File::get($this->workspace->metadataPath))->toBe($first);
});

it('drops a pruned entry with its own comment but rehomes the section banner', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            // ----------------------------------------------------------
            // tabler.*: our default set
            // ----------------------------------------------------------

            // This icon was deleted from the vendor set months ago.
            'tabler.ghost' => [
                'label' => 'Gone',
            ],

            'tabler.home' => [
                'label' => 'Home',
            ],
        PHP);

    $result = app(IconRegistry::class)->cleanMetadata(write: true);

    expect($result['removed'])->toBe(['tabler.ghost']);

    $rewritten = File::get($this->workspace->metadataPath);

    expect($rewritten)
        ->not->toContain('tabler.ghost')
        ->not->toContain('This icon was deleted from the vendor set months ago.')
        ->toContain('// tabler.*: our default set');

    // The banner documents the whole vendor block, so it moves to what is left.
    expect($rewritten)->toMatch('/tabler\.\*: our default set.*?\n.*?\n\n    \'tabler\.home\'/s');
});

it('writes a usable file when no metadata file exists yet', function (): void {
    expect(File::exists($this->workspace->metadataPath))->toBeFalse();

    app(IconRegistry::class)->cleanMetadata(write: true);

    expect(File::get($this->workspace->metadataPath))->toContain('| Icon metadata');
    expect(require $this->workspace->metadataPath)->toBe([]);
});

it('keeps a comment written inside an entry with that entry', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            'tabler.home' => [
                'label' => 'Home',
                // Local term first, English second, because the search box is local.
                'aliases' => ['house', 'home'],
            ],

            'tabler.trash' => [
                'label' => 'Remove',
            ],
        PHP);

    app(IconRegistry::class)->cleanMetadata(write: true);

    $rewritten = File::get($this->workspace->metadataPath);

    // It cannot go back between the fields, but it must not drift onto trash.
    expect($rewritten)->toMatch('/Local term first.*?\n\s*\'tabler\.home\'/s');
});

it('does not mistake a note that mentions dashes for a section banner', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            // Covers the 2024---2026 vintages, see the ingest notes.
            'tabler.ghost' => [
                'label' => 'Gone',
            ],

            'tabler.home' => [
                'label' => 'Home',
            ],
        PHP);

    app(IconRegistry::class)->cleanMetadata(write: true);

    // The note belongs to ghost alone, so pruning ghost takes it along.
    expect(File::get($this->workspace->metadataPath))->not->toContain('2024---2026');
});

it('round-trips values containing quotes and nested custom fields', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            'tabler.home' => [
                'label' => "Director's",
                'aliases' => ['house'],
                'tags' => ['nav' => ['primary', 'sidebar']],
            ],
        PHP);

    $registry = app(IconRegistry::class);

    $registry->cleanMetadata(write: true);
    $first = File::get($this->workspace->metadataPath);

    $registry->cleanMetadata(write: true);

    expect(File::get($this->workspace->metadataPath))->toBe($first);

    expect(require $this->workspace->metadataPath)->toBe([
        'tabler.home' => [
            'label' => "Director's",
            'aliases' => ['house'],
            'tags' => ['nav' => ['primary', 'sidebar']],
        ],
    ]);
});

it('rewrites a file that does not put `return [` on a line of its own', function (): void {
    File::ensureDirectoryExists(dirname($this->workspace->metadataPath));
    File::put($this->workspace->metadataPath, '<?php return '.var_export([
        'tabler.ghost' => ['label' => 'Gone'],
        'tabler.home' => ['label' => 'Home'],
    ], true).';');

    app(IconRegistry::class)->cleanMetadata(write: true);

    expect(require $this->workspace->metadataPath)->toBe(['tabler.home' => ['label' => 'Home']]);
});

it('reads a rewritten file straight away, not the copy opcache still holds', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, "    'tabler.ghost' => ['label' => 'Gone'],\n    'tabler.home' => ['label' => 'Home'],");

    // Opcache leaves a file alone for its first seconds; an older file is what it holds on to.
    touch($this->workspace->metadataPath, time() - 60);
    $registry = app(IconRegistry::class);
    $registry->metadata()->read();

    $registry->cleanMetadata(write: true);

    expect($registry->metadata()->read())->toBe(['tabler.home' => ['label' => 'Home']]);
})->skip(fn (): bool => ! filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL), 'Needs opcache.enable_cli, which CI turns on.');

it('replaces the metadata file rather than rewriting it in place', function (): void {
    $this->workspace->metadata(['tabler.home' => ['label' => 'Home']]);
    $inode = fileinode($this->workspace->metadataPath);

    app(IconRegistry::class)->metadata()->write(['tabler.home' => ['label' => 'Home']]);
    clearstatcache();

    expect(fileinode($this->workspace->metadataPath))->not->toBe($inode);
});

it('keeps the file mode of a metadata file it rewrites', function (): void {
    $this->workspace->metadata(['tabler.home' => ['label' => 'Home']]);
    chmod($this->workspace->metadataPath, 0640);

    app(IconRegistry::class)->metadata()->write(['tabler.home' => ['label' => 'Home']]);
    clearstatcache();

    expect(fileperms($this->workspace->metadataPath) & 0777)->toBe(0640);
});

it('creates a metadata file that is not executable', function (): void {
    app(IconRegistry::class)->metadata()->write(['tabler.home' => ['label' => 'Home']]);
    clearstatcache();

    expect(fileperms($this->workspace->metadataPath) & 0777)->toBe(0666 & ~umask());
});

it('keeps the order of the entries when it prunes, so a section stays under its banner', function (): void {
    writeMetadataFixture($this->workspace->metadataPath, <<<'PHP'

            // ----------------------------------------------------------
            // tabler.*: our default set
            // ----------------------------------------------------------

            'tabler.trash' => [
                'label' => 'Delete',
            ],

            'tabler.gone' => [
                'label' => 'Gone',
            ],

            'tabler.home' => [
                'label' => 'Home',
            ],

            'acme.labels' => [
                'label' => 'Labels',
            ],
        PHP);

    app(IconRegistry::class)->cleanMetadata(write: true);

    $written = File::get($this->workspace->metadataPath);

    expect(array_keys(require $this->workspace->metadataPath))->toBe(['tabler.trash', 'tabler.home', 'acme.labels'])
        ->and(strpos($written, 'our default set'))->toBeLessThan(strpos($written, "'tabler.trash'"));
});
