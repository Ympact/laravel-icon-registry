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
    public const WEIGHT_CONTEXT = 3;

    protected const WEIGHT_PATH = 1;

    protected const MINIMUM_WORD_LENGTH = 3;

    /** How far a closing tag may be for the text before it to still describe the icon. */
    protected const MAXIMUM_SLOT_LENGTH = 500;

    /** How many lines either side of a PHP reference can hold its label. */
    protected const CONTEXT_LINES = 3;

    /** Quoted values and `{{ }}` are skipped whole, so a `>` inside them does not end the tag. */
    protected const TAG_PATTERN = '/<((?:flux:|x-)[\w.:\-]*)((?:\{\{.*?\}\}|"[^"]*+"|\'[^\']*+\'|[-=]>|[^\'">])*+)>/s';

    protected const ATTRIBUTE_PATTERN = '/(?<![\w:.\-])([:@\w.\-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s';

    protected const ICON_ATTRIBUTES = ['icon', 'icon:leading', 'icon:trailing', 'icon-leading', 'icon-trailing'];

    protected const CONTEXT_ATTRIBUTES = ['label', 'title', 'aria-label', 'tooltip', 'heading', 'placeholder', 'href'];

    protected const CALL_PATTERN = '/(?:(?<![\w>:$])@?svg|\bIcons::render)\(\s*([\'"])(.+?)\1/';

    protected const ICON_VALUE_PATTERN = '/(?:[\'"]\w*icon\w*[\'"]\s*=>|\$\w*icon\w*\s*=|\bicon:|->\w*icon\w*\()\s*([\'"])([\w.:\-]+)\1/i';

    protected const STORED_KEY_PATTERN = '/([\'"])([\w\-]+\.[\w.\-]+)\1/';

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
        $found = [...$this->referencesInTags($source, $resolver), ...$this->referencesInPhp($source, $resolver)];

        $usages = [];

        foreach ($found as $reference) {
            $key = $resolver[$reference['reference']] ?? null;
            $dedupe = ($key ?? $reference['reference']).':'.$reference['line'];

            // A tag is read first, so its richer context survives when PHP patterns see it again.
            $usages[$dedupe] ??= new IconUsage(
                reference: $reference['reference'],
                key: $key,
                file: $displayPath,
                line: $reference['line'],
                keywords: $this->keywords($reference['words'], $pathWeights, $key),
                dynamic: $reference['dynamic'],
            );
        }

        return array_values($usages);
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
     * @return list<array{reference: string, line: int, words: list<string>, dynamic: bool}>
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
                $found[] = ['reference' => $reference, 'line' => $line, 'words' => $words, 'dynamic' => $dynamic];
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
        if ($value === '') {
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

        return (string) preg_replace(['/<[^>]*>/', '/@\w+(\([^)]*\))?/', '/\$\w+/', '#https?://[^/\s]+#'], ' ', $echoesResolved);
    }

    /**
     * @param  array<string, string>  $resolver
     * @return list<array{reference: string, line: int, words: list<string>, dynamic: bool}>
     */
    protected function referencesInPhp(string $source, array $resolver): array
    {
        $lines = explode("\n", $source);
        $found = [];

        foreach ([self::CALL_PATTERN => true, self::ICON_VALUE_PATTERN => false, self::STORED_KEY_PATTERN => false] as $pattern => $strict) {
            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $reference = $match[2][0];

                if (! isset($resolver[$reference]) && ! $strict) {
                    continue;
                }

                $line = $this->lineAt($source, $match[0][1]);

                $found[] = ['reference' => $reference, 'line' => $line, 'words' => $this->nearbyLabelWords($lines, $line), 'dynamic' => false];
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    protected function nearbyLabelWords(array $lines, int $line): array
    {
        $start = max(0, $line - 1 - self::CONTEXT_LINES);
        $window = implode("\n", array_slice($lines, $start, self::CONTEXT_LINES * 2 + 1));

        preg_match_all(self::LABEL_PATTERN, $window, $matches);

        return $this->words(implode(' ', $matches[1]));
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
