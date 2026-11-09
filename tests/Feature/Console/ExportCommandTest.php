<?php

use Illuminate\Support\Facades\File;
use Tests\Support\IconWorkspace;

use function Pest\Laravel\artisan;

/**
 * The manifest is the contract with the editor extensions, which cannot run the PHP registry.
 */
beforeEach(function (): void {
    $this->workspace = (new IconWorkspace)
        ->flux(['tabler' => ['trash']])
        ->metadata(['tabler.trash' => ['label' => 'Remove', 'aliases' => ['dustbin'], 'use_cases' => ['action.delete']]]);

    $this->path = "{$this->workspace->root}/editor/icons.json";
    config()->set('icon-registry.editor_manifest', $this->path);
});

afterEach(fn () => $this->workspace->delete());

it('writes the icons and the tag editors should complete', function (): void {
    artisan('icon-registry:export')->expectsOutputToContain('Exported 1 icons')->assertSuccessful();

    $manifest = File::json($this->path);

    expect($manifest['component'])->toBe('registry-icon')
        ->and($manifest['icons'][0])->toMatchArray([
            'key' => 'tabler.trash',
            'label' => 'Remove',
            'aliases' => ['dustbin'],
            'use_cases' => ['action.delete'],
        ])
        ->and($manifest['icons'][0])->not->toHaveKey('terms')
        ->and($manifest['icons'][0])->not->toHaveKey('search_text');
});

it('lists icons added after the cache was written, so completion is never behind it', function (): void {
    artisan('icon-registry:cache')->assertSuccessful();
    $this->workspace->flux(['tabler' => ['star']]);

    artisan('icon-registry:export')->assertSuccessful();

    expect(array_column(File::json($this->path)['icons'], 'key'))->toContain('tabler.star');
});

it('writes to the path it is given', function (): void {
    $path = "{$this->workspace->root}/elsewhere.json";

    artisan('icon-registry:export', ['--path' => $path])->assertSuccessful();

    expect($path)->toBeFile()->and($this->path)->not->toBeFile();
});

it('speaks the manifest version the VS Code extension reads', function (): void {
    artisan('icon-registry:export')->assertSuccessful();

    $source = (string) file_get_contents(dirname(__DIR__, 3).'/editors/vscode/src/manifest.ts');

    expect(preg_match('/SUPPORTED_MANIFEST_VERSION = (\d+)/', $source, $match))
        ->toBe(1, 'editors/vscode/src/manifest.ts must declare SUPPORTED_MANIFEST_VERSION.')
        ->and((int) $match[1])->toBe(File::json($this->path)['version'], 'Update SUPPORTED_MANIFEST_VERSION with the manifest format.');
});
