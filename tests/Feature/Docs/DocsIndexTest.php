<?php

/**
 * Every Markdown page under docs/, except the authoring stubs, relative to docs/.
 *
 * @return list<string>
 */
function documentationPages(): array
{
    $root = dirname(__DIR__, 3).'/docs';
    $pages = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);

        if ($file->getExtension() === 'md' && $path !== 'index.md' && ! str_starts_with($path, 'stubs/')) {
            $pages[] = $path;
        }
    }

    sort($pages);

    return $pages;
}

it('links every documentation page from the index', function (): void {
    $index = (string) file_get_contents(dirname(__DIR__, 3).'/docs/index.md');

    expect(documentationPages())->not->toBeEmpty();

    foreach (documentationPages() as $page) {
        expect(str_contains($index, "]({$page})"))->toBeTrue("Link docs/{$page} from docs/index.md.");
    }
});

it('links only to pages that exist', function (): void {
    $index = (string) file_get_contents(dirname(__DIR__, 3).'/docs/index.md');
    preg_match_all('/\]\(([^)#]+\.md)\)/', $index, $links);

    foreach ($links[1] as $link) {
        expect(file_exists(dirname(__DIR__, 3).'/docs/'.$link))->toBeTrue("docs/index.md links to docs/{$link}, which does not exist.");
    }
});
