<?php

namespace Ympact\IconRegistry\Actions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Ympact\IconRegistry\Icon;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconUsage;
use Ympact\IconRegistry\IconUsageReport;

/**
 * Finds the icons an application's PHP and Blade source refers to, by pattern rather than by
 * compiling it, and gathers words from around each reference that describe what the icon is for.
 */
class ScanIconUsages
{
    /** A word from the reference's own label, slot or action says more than one from its file path. */
    protected const WEIGHT_CONTEXT = 3;

    protected const WEIGHT_PATH = 1;

    protected const MINIMUM_WORD_LENGTH = 3;

    /** How far a closing tag may be for the text before it to still describe the icon. */
    protected const MAXIMUM_SLOT_LENGTH = 500;

    /** How many bytes either side of a PHP reference to search for the bounds of its array or chain. */
    protected const CONTEXT_REACH = 400;

    /** How far back to look for the function a string is the first argument of. */
    protected const LOOKUP_REACH = 40;

    /** Quoted values and `{{ }}` are skipped whole, so a `>` inside them does not end the tag. */
    protected const TAG_PATTERN = '/<((?:flux:|x-)[\w.:\-]*)((?:\{\{.*?\}\}|"[^"]*+"|\'[^\']*+\'|[-=]>|[^\'">])*+)>/s';

    protected const ATTRIBUTE_PATTERN = '/(?<![\w:.\-])([:@\w.\-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s';

    protected const ICON_ATTRIBUTES = ['icon', 'icon:leading', 'icon:trailing', 'icon-leading', 'icon-trailing'];

    protected const CONTEXT_ATTRIBUTES = ['label', 'title', 'aria-label', 'tooltip', 'heading', 'placeholder', 'href'];

    protected const CALL_PATTERN = '/(?:(?<![\w>:$])@?svg|\bIcons::render)\(\s*([\'"])([^\'"]+)\1(?=\s*[,)])/';

    protected const ICON_VALUE_PATTERN = '/(?:[\'"]\w*icon\w*[\'"]\s*=>|\$\w*icon\w*\s*=|\bicon:|->\w*icon\w*\()\s*([\'"])([\w.:\-]+)\1/i';

    protected const STORED_KEY_PATTERN = '/([\'"])([\w\-]+\.[\w.\-]+)\1/';

    /** A string here names a route, config value, view or translation, even if it equals an icon key. */
    protected const NAMED_LOOKUP_PATTERN = '/(?:\b(?:route|to_route|config|view|__|trans|trans_choice|lang|include|includeIf|extends|can|cannot)|->(?:name|route|view|can)|::get)\(\s*$/i';

    /** Bound values that switch an icon off rather than choose one at runtime. */
    protected const NO_ICON_EXPRESSIONS = ['null', 'false', "''", '""'];

    protected const LABEL_PATTERN = '/(?:[\'"]?\b(?:label|title|heading|tooltip)[\'"]?\s*(?:=>|:)|->(?:label|title|heading|tooltip)\()\s*(?:__\(\s*)?[\'"]([^\'"]+)[\'"]/i';

    protected const STOP_WORDS = [
        'and', 'are', 'for', 'from', 'het', 'een', 'met', 'not', 'the', 'this', 'van', 'voor', 'with', 'you', 'your',
        'app', 'blade', 'component', 'components', 'config', 'controller', 'controllers', 'flux', 'http', 'index',
        'layout', 'layouts', 'livewire', 'model', 'models', 'page', 'pages', 'partial', 'partials', 'php',
        'resource', 'resources', 'view', 'views',
    ];

    public function __construct(
        protected IconRegistry $registry,
        protected Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $directories  Absolute paths; each is walked recursively.
     */
    public function handle(array $directories): IconUsageReport
    {
        $resolver = $this->resolver();
        $usages = [];

        foreach ($directories as $directory) {
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() === 'php') {
                    array_push($usages, ...$this->scanFile($file->getPathname(), $file->getRelativePathname(), $resolver));
                }
            }
        }

        return new IconUsageReport($usages);
    }

    /**
     * Reference and key both resolve to the key; a key wins where the two collide.
     *
     * @return array<string, string>
     */
    protected function resolver(): array
    {
        $icons = $this->registry->icons(fresh: true);

        return $icons->mapWithKeys(fn (Icon $icon) => [$icon->reference => $icon->key])
            ->merge($icons->mapWithKeys(fn (Icon $icon) => [$icon->key => $icon->key]))
            ->all();
    }

    /**
     * @param  array<string, string>  $resolver
     * @return list<IconUsage>
     */
    protected function scanFile(string $path, string $relativePath, array $resolver): array
    {
        $source = $this->withoutComments($path);
        $displayPath = Str::after($path, base_path().DIRECTORY_SEPARATOR);
        $pathWeights = $this->weigh($this->words(str_replace('.blade.php', '', $relativePath)), self::WEIGHT_PATH);
        $inTags = $this->referencesInTags($source, $resolver);
        $usages = [];

        foreach ([...$inTags, ...$this->referencesInPhp($source, $resolver, $inTags)] as $reference) {
            $key = $resolver[$reference['reference']] ?? null;

            $usages[] = new IconUsage(
                reference: $reference['reference'],
                key: $key,
                file: $displayPath,
                line: $reference['line'],
                keywords: $this->keywords($reference['words'], $pathWeights, $key),
                contextKeywords: array_values(array_unique(array_diff($reference['words'], $this->words((string) $key)))),
                dynamic: $reference['dynamic'],
            );
        }

        return $usages;
    }

    /**
     * Comments are replaced by their line breaks, so a commented-out icon is not reported and
     * every line number stays true.
     */
    protected function withoutComments(string $path): string
    {
        $contents = $this->files->get($path);
        $lineBreaks = fn (string $comment) => str_repeat("\n", substr_count($comment, "\n"));

        if (str_ends_with($path, '.blade.php')) {
            return (string) preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $match) => $lineBreaks($match[0]), $contents);
        }

        $source = '';

        foreach (token_get_all($contents) as $token) {
            if (is_string($token)) {
                $source .= $token;
            } else {
                $source .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? $lineBreaks($token[1]) : $token[1];
            }
        }

        return $source;
    }

    /**
     * @param  array<string, string>  $resolver
     * @return list<array{reference: string, line: int, words: list<string>, dynamic: bool, start: int, end: int}>
     */
    protected function referencesInTags(string $source, array $resolver): array
    {
        preg_match_all(self::TAG_PATTERN, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $found = [];

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $tag = $match[1][0];
            $attributes = $this->attributes($match[2][0]);
            $references = $this->tagReferences($tag, $attributes, $resolver);

            if ($references === []) {
                continue;
            }

            $selfClosing = str_ends_with(rtrim($match[2][0]), '/');
            $words = $this->tagWords($attributes, $this->textBeside($source, $tag, $offset + strlen($whole), $selfClosing));
            $line = $this->lineAt($source, $offset);

            foreach ($references as [$reference, $dynamic]) {
                $found[] = ['reference' => $reference, 'line' => $line, 'words' => $words, 'dynamic' => $dynamic, 'start' => $offset, 'end' => $offset + strlen($whole)];
            }
        }

        return $found;
    }

    /**
     * @return array<string, array{value: string, bound: bool}>
     */
    protected function attributes(string $raw): array
    {
        preg_match_all(self::ATTRIBUTE_PATTERN, $raw, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            $bound = str_starts_with($match[1], ':');
            $attributes[ltrim($match[1], ':')] = ['value' => $match[3] ?? $match[2] ?? '', 'bound' => $bound];
        }

        return $attributes;
    }

    /**
     * The references a tag makes. An unresolved literal is kept only where the tag can mean
     * nothing but an icon; `<x-card icon="x">` may well be something else.
     *
     * @param  array<string, array{value: string, bound: bool}>  $attributes
     * @param  array<string, string>  $resolver
     * @return list<array{0: string, 1: bool}> Each reference with whether it is dynamic.
     */
    protected function tagReferences(string $tag, array $attributes, array $resolver): array
    {
        if (str_starts_with($tag, 'flux:icon.')) {
            return [[substr($tag, strlen('flux:icon.')), false]];
        }

        $isIconTag = in_array($tag, ['flux:icon', 'x-icon', 'x-icon-registry::icon'], true);
        $strict = $isIconTag || str_starts_with($tag, 'flux:');
        $names = $isIconTag ? [...self::ICON_ATTRIBUTES, 'name'] : self::ICON_ATTRIBUTES;

        $references = [];

        foreach (array_intersect_key($attributes, array_flip($names)) as $attribute) {
            array_push($references, ...$this->attributeReferences($attribute['value'], $attribute['bound'], $strict, $resolver));
        }

        // Blade Icons registers each icon as a component: <x-heroicon-o-home />.
        if (str_starts_with($tag, 'x-') && isset($resolver[substr($tag, 2)])) {
            $references[] = [substr($tag, 2), false];
        }

        return $references;
    }

    /**
     * @param  array<string, string>  $resolver
     * @return list<array{0: string, 1: bool}>
     */
    protected function attributeReferences(string $value, bool $bound, bool $strict, array $resolver): array
    {
        if ($value === '' || ($bound && in_array(strtolower(trim($value)), self::NO_ICON_EXPRESSIONS, true))) {
            return [];
        }

        if (! $bound && ! preg_match('/\{\{|\{!!|\$/', $value)) {
            return isset($resolver[$value]) || $strict ? [[$value, false]] : [];
        }

        // An expression may still name its icons: :icon="$open ? 'chevron-up' : 'chevron-down'".
        $literals = $this->quotedStrings($value);
        $known = array_values(array_filter($literals, fn (string $literal) => isset($resolver[$literal])));

        if ($known !== []) {
            return array_map(fn (string $literal) => [$literal, false], $known);
        }

        $isSingleLiteral = count($literals) === 1 && trim($value, " \t\n'\"") === $literals[0];

        if ($isSingleLiteral) {
            return $strict ? [[$literals[0], false]] : [];
        }

        return [[$value, true]];
    }

    /**
     * @param  array<string, array{value: string, bound: bool}>  $attributes
     * @return list<string>
     */
    protected function tagWords(array $attributes, string $text): array
    {
        $words = $this->words($this->readable($text));

        foreach ($attributes as $name => $attribute) {
            if (in_array($name, self::CONTEXT_ATTRIBUTES, true) || str_starts_with($name, 'wire:click')) {
                $value = $attribute['bound'] ? "{{ {$attribute['value']} }}" : $attribute['value'];

                array_push($words, ...$this->words($this->readable($value)));
            }
        }

        return $words;
    }

    /**
     * The tag's slot, or for a self-closing tag the text after it on the same line, which is
     * where `<flux:icon.trash /> Delete` keeps its meaning.
     */
    protected function textBeside(string $source, string $tag, int $tagEnd, bool $selfClosing): string
    {
        if ($selfClosing) {
            return Str::before(Str::before(substr($source, $tagEnd), "\n"), '<');
        }

        $closing = strpos($source, "</{$tag}>", $tagEnd);

        if ($closing === false || $closing - $tagEnd > self::MAXIMUM_SLOT_LENGTH) {
            return '';
        }

        return substr($source, $tagEnd, $closing - $tagEnd);
    }

    /**
     * Reduces Blade to its human words: an echo counts only for the strings inside it, so
     * `{{ __('Delete') }}` reads "Delete" and `{{ $user->name }}` reads nothing.
     */
    protected function readable(string $value): string
    {
        $echoesResolved = (string) preg_replace_callback(
            '/\{\{.*?\}\}|\{!!.*?!!\}/s',
            fn (array $match) => ' '.implode(' ', $this->quotedStrings($match[0])).' ',
            $value,
        );

        return (string) preg_replace(['/<[^>]*>/', '/@\w+(\s*(\((?:[^()]++|(?2))*\)))?/', '/\$\w+(?:(?:\??->|::)\w+)*/', '#https?://[^/\s]+#'], ' ', $echoesResolved);
    }

    /**
     * @param  array<string, string>  $resolver
     * @param  list<array{reference: string, line: int, words: list<string>, dynamic: bool, start: int, end: int}>  $inTags
     * @return list<array{reference: string, line: int, words: list<string>, dynamic: bool, start: int, end: int}>
     */
    protected function referencesInPhp(string $source, array $resolver, array $inTags): array
    {
        $found = [];

        foreach ([self::CALL_PATTERN => true, self::ICON_VALUE_PATTERN => false, self::STORED_KEY_PATTERN => false] as $pattern => $strict) {
            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                [$reference, $literalOffset] = $match[2];
                $offset = $match[0][1];

                // Several patterns can match one literal, and a tag has already read its own.
                if (isset($found[$literalOffset]) || (! $strict && ! isset($resolver[$reference])) || $this->isReadByTag($reference, $literalOffset, $inTags)) {
                    continue;
                }

                if ($pattern === self::STORED_KEY_PATTERN && $this->isNamedLookup($source, $offset)) {
                    continue;
                }

                $found[$literalOffset] = [
                    'reference' => $reference,
                    'line' => $this->lineAt($source, $offset),
                    'words' => $this->labelWordsAround($source, $offset, str_starts_with($match[0][0], '->')),
                    'dynamic' => false,
                    'start' => $offset,
                    'end' => $offset + strlen($match[0][0]),
                ];
            }
        }

        return array_values($found);
    }

    /**
     * @param  list<array{reference: string, line: int, words: list<string>, dynamic: bool, start: int, end: int}>  $inTags
     */
    protected function isReadByTag(string $reference, int $offset, array $inTags): bool
    {
        foreach ($inTags as $tag) {
            if ($tag['reference'] === $reference && $offset >= $tag['start'] && $offset < $tag['end']) {
                return true;
            }
        }

        return false;
    }

    protected function isNamedLookup(string $source, int $offset): bool
    {
        $start = max(0, $offset - self::LOOKUP_REACH);

        return preg_match(self::NAMED_LOOKUP_PATTERN, substr($source, $start, $offset - $start)) === 1;
    }

    /**
     * Labels that belong to the same item as the reference: its own array, or for a fluent call
     * its own chain. A label nested deeper belongs to something inside that item.
     *
     * @return list<string>
     */
    protected function labelWordsAround(string $source, int $offset, bool $isFluentCall): array
    {
        $start = $this->itemBoundary($source, $offset - 1, -1, $isFluentCall);
        $item = substr($source, $start, $this->itemBoundary($source, $offset, 1, $isFluentCall) - $start);

        preg_match_all(self::LABEL_PATTERN, $item, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $words = [];

        foreach ($matches as $match) {
            $before = substr($item, 0, $match[0][1]);

            if (preg_match_all('/[(\[{]/', $before) === preg_match_all('/[)\]}]/', $before)) {
                array_push($words, ...$this->words($match[1][0]));
            }
        }

        return $words;
    }

    /**
     * The offset where the item around a position ends, walking in one direction: the first
     * unmatched bracket or statement end, and for a fluent call also the next list comma.
     *
     * @param  int  $step  -1 to walk backwards, 1 to walk forwards.
     */
    protected function itemBoundary(string $source, int $from, int $step, bool $stopAtComma): int
    {
        $depth = 0;
        $length = strlen($source);

        for ($position = $from, $walked = 0; $position >= 0 && $position < $length && $walked < self::CONTEXT_REACH; $position += $step, $walked++) {
            $character = $source[$position];
            $opens = str_contains($step === 1 ? '([{' : ')]}', $character);
            $closes = str_contains($step === 1 ? ')]}' : '([{', $character);

            if ($opens) {
                $depth++;
            } elseif ($closes && $depth > 0) {
                $depth--;
            } elseif ($closes || ($depth === 0 && ($character === ';' || ($stopAtComma && $character === ',')))) {
                return $step === 1 ? $position : $position + 1;
            }
        }

        return $step === 1 ? min($position, $length) : max($position + 1, 0);
    }

    /**
     * @param  list<string>  $contextWords
     * @param  array<string, int>  $pathWeights
     * @return array<string, int>
     */
    protected function keywords(array $contextWords, array $pathWeights, ?string $key): array
    {
        $weights = $this->weigh($contextWords, self::WEIGHT_CONTEXT);

        foreach ($pathWeights as $word => $weight) {
            $weights[$word] = ($weights[$word] ?? 0) + $weight;
        }

        // Calling the trash icon "trash" adds nothing to what its key already says.
        $ownWords = $key === null ? [] : array_flip($this->words($key));

        return array_diff_key($weights, $ownWords);
    }

    /**
     * @param  list<string>  $words
     * @return array<string, int>
     */
    protected function weigh(array $words, int $weight): array
    {
        $weights = [];

        foreach ($words as $word) {
            $weights[$word] = ($weights[$word] ?? 0) + $weight;
        }

        return $weights;
    }

    /**
     * @return list<string>
     */
    protected function words(string $text): array
    {
        // Split camelCase only at a lower-to-upper step, so an acronym like FAQ stays one word.
        $separated = (string) preg_replace('/(\p{Ll})(\p{Lu})/u', '$1 $2', $text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $separated, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(mb_strtolower(...), $words),
            fn (string $word) => mb_strlen($word) >= self::MINIMUM_WORD_LENGTH
                && ! is_numeric($word)
                && ! in_array($word, self::STOP_WORDS, true),
        ));
    }

    /**
     * @return list<string>
     */
    protected function quotedStrings(string $expression): array
    {
        preg_match_all('/\'([^\']+)\'|"([^"]+)"/', $expression, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => $match[2] ?? $match[1] ?? '', $matches);
    }

    protected function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }
}
