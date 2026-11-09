<?php

namespace Ympact\IconRegistry;

use JsonSerializable;

/**
 * One place in the application's source that refers to an icon, with the context words found there.
 * It is resolved when the key is set, dynamic when only a runtime expression names the icon.
 */
final readonly class IconUsage implements JsonSerializable
{
    /**
     * @param  string  $reference  The icon name as written, or the expression when dynamic.
     * @param  string|null  $key  The registry key, or null when the reference did not resolve.
     * @param  array<string, int>  $keywords  Context words, each weighted by where it was found.
     * @param  list<string>  $contextKeywords  Those found at the reference itself, not in the file path.
     */
    public function __construct(
        public string $reference,
        public ?string $key,
        public string $file,
        public int $line,
        public array $keywords = [],
        public array $contextKeywords = [],
        public bool $dynamic = false,
    ) {}

    /**
     * @return array{reference: string, file: string, line: int, keywords: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'reference' => $this->reference,
            'file' => $this->file,
            'line' => $this->line,
            'keywords' => array_keys($this->keywords),
        ];
    }
}
