<?php

namespace Ympact\IconRegistry;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The application's icon metadata file: labels and aliases keyed by icon key. It is
 * rewritten by hand rather than with var_export(), so its header and the comments documenting
 * each entry survive a rewrite.
 */
class MetadataFile
{
    /**
     * Written only when the file does not exist yet; an existing file keeps its own header.
     */
    protected const HEADER = <<<'PHP'
        <?php

        /*
        |--------------------------------------------------------------------------
        | Icon metadata
        |--------------------------------------------------------------------------
        |
        | Extra search and documentation data for the icon registry, keyed by the
        | icon key "<vendor>.<name>", e.g. "tabler.home".
        |
        */

        PHP;

    /** The fields the registry reads; anything else in an entry is carried through untouched. */
    protected const KNOWN_FIELDS = ['label', 'aliases'];

    public function __construct(
        protected Filesystem $files,
        protected string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function read(): array
    {
        if (! $this->files->exists($this->path)) {
            return [];
        }

        return $this->files->getRequire($this->path);
    }

    /**
     * Trims the known fields and drops empty ones; custom fields such as a category pass through.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public function normalizeEntry(array $entry): array
    {
        $label = isset($entry['label']) ? trim((string) $entry['label']) : null;

        return array_filter([
            'label' => $label,
            'aliases' => $this->normalizeList($entry['aliases'] ?? []),
            ...Arr::except($entry, self::KNOWN_FIELDS),
        ], fn (mixed $value) => filled($value));
    }

    /**
     * @param  array<string, array<string, mixed>>  $metadata
     */
    public function write(array $metadata): void
    {
        ['header' => $header, 'comments' => $comments] = $this->parseSource();

        $comments = $this->rehomeBanners($comments, array_keys($metadata));

        $body = '';

        foreach ($metadata as $key => $entry) {
            if ($banner = $comments[$key]['banner'] ?? null) {
                $body .= $banner."\n\n";
            }

            if ($note = $comments[$key]['note'] ?? null) {
                $body .= $note."\n";
            }

            $body .= $this->renderEntry($key, $entry);
        }

        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, rtrim($header)."\n\nreturn [\n\n".$body."];\n");

        // Opcache would otherwise serve the previous file to a `require` within its revalidate window.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    protected function normalizeList(iterable $values): array
    {
        $trimmed = collect($values)->map(fn (mixed $value) => trim((string) $value))->filter()->all();

        return array_values(array_unique($trimmed));
    }

    /**
     * Splits the file into its header and the comments preceding each top-level entry, telling
     * a section banner (a ruled block heading a vendor's entries) apart from a note on the entry.
     *
     * @return array{header: string, comments: array<string, array{banner: ?string, note: ?string}>}
     */
    protected function parseSource(): array
    {
        if (! $this->files->exists($this->path)) {
            return ['header' => self::HEADER, 'comments' => []];
        }

        $header = [];
        $comments = [];
        $blocks = [];
        $block = [];
        $current = null;
        $depth = 0;
        $inBody = false;

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $this->files->get($this->path)));

        foreach ($lines as $line) {
            if (! $inBody) {
                if (preg_match('/^\s*return\s*\[/', $line)) {
                    $inBody = true;

                    continue;
                }

                $header[] = $line;

                continue;
            }

            if (preg_match('/^\s*(?:\/\/|#|\/\*|\*)/', $line)) {
                $block[] = rtrim($line);

                continue;
            }

            // A blank line closes a comment block without detaching it from what follows.
            if ($block !== []) {
                $blocks[] = implode("\n", $block);
                $block = [];
            }

            if (trim($line) === '') {
                continue;
            }

            if ($depth === 0 && preg_match('/^\s*[\'"]([^\'"]+)[\'"]\s*=>/', $line, $match)) {
                $current = $match[1];

                if ($blocks !== []) {
                    $comments[$current] = $this->splitComments($blocks);
                }

                $blocks = [];
            } elseif ($depth > 0 && $blocks !== [] && $current !== null) {
                // A comment inside an entry cannot be rendered back in place, but it documents
                // that entry, so keep it there rather than let it drift onto the next one.
                $comments[$current]['banner'] ??= null;
                $comments[$current]['note'] = trim(
                    ($comments[$current]['note'] ?? '')."\n\n".implode("\n\n", $blocks),
                    "\n"
                );

                $blocks = [];
            } else {
                $blocks = [];
            }

            // Blank out string literals first, so brackets inside them do not count.
            $code = preg_replace('/([\'"]).*?\1/', "''", $line) ?? $line;
            $depth += substr_count($code, '[') - substr_count($code, ']');
        }

        // Not laid out as `return [` on a line of its own, e.g. var_export() output: keep what
        // precedes the return statement, as the comments cannot be told apart from the code.
        if (! $inBody) {
            return ['header' => Str::before(implode("\n", $header), 'return') ?: self::HEADER, 'comments' => []];
        }

        return [
            'header' => implode("\n", $header),
            'comments' => $comments,
        ];
    }

    /**
     * @param  list<string>  $blocks
     * @return array{banner: ?string, note: ?string}
     */
    protected function splitComments(array $blocks): array
    {
        $banners = array_filter(
            $blocks,
            fn (string $block) => preg_match('/^\s*(?:\/\/|#)\s*-{3,}\s*$/m', $block) === 1
        );
        $notes = array_diff_key($blocks, $banners);

        return [
            'banner' => $banners === [] ? null : implode("\n", $banners),
            'note' => $notes === [] ? null : implode("\n\n", $notes),
        ];
    }

    /**
     * A note on a pruned entry leaves with it. A section banner heads the whole vendor block,
     * so it moves to the first entry of that vendor still there.
     *
     * @param  array<string, array{banner: ?string, note: ?string}>  $comments
     * @param  list<string>  $keys
     * @return array<string, array{banner: ?string, note: ?string}>
     */
    protected function rehomeBanners(array $comments, array $keys): array
    {
        $surviving = array_flip($keys);

        foreach ($comments as $key => $comment) {
            if (isset($surviving[$key])) {
                continue;
            }

            unset($comments[$key]);

            if ($comment['banner'] === null) {
                continue;
            }

            $heir = Arr::first(
                $keys,
                fn (string $candidate) => Str::before($candidate, '.') === Str::before($key, '.')
                    && ($comments[$candidate]['banner'] ?? null) === null
            );

            if ($heir !== null) {
                $comments[$heir]['banner'] = $comment['banner'];
                $comments[$heir]['note'] ??= null;
            }
        }

        return $comments;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function renderEntry(string $key, array $entry): string
    {
        $lines = ['    '.var_export($key, true).' => ['];

        foreach ($entry as $field => $value) {
            $lines[] = '        '.var_export($field, true).' => '.$this->exportValue($value).',';
        }

        $lines[] = '    ],';

        return implode("\n", $lines)."\n\n";
    }

    /**
     * Exports a value on one line. Arrays nest, because custom fields need not be flat.
     */
    protected function exportValue(mixed $value): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        $isList = array_is_list($value);

        return '['.collect($value)
            ->map(fn (mixed $item, int|string $key) => $isList
                ? $this->exportValue($item)
                : var_export($key, true).' => '.$this->exportValue($item))
            ->implode(', ').']';
    }
}
