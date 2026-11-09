<?php

namespace Ympact\IconRegistry;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One icon in the registry: the key an application stores, the provider that draws it, and the
 * metadata search runs against.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Icon implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $key  "<vendor>.<name>", unique across providers; the value to store.
     * @param  string  $reference  What the provider's component expects, e.g. "home" or "heroicon-o-home".
     * @param  list<string>  $aliases
     * @param  list<string>  $useCases
     * @param  array<string, int>  $terms  Lower-cased search terms, each weighted by where it came from.
     * @param  string  $searchText  The terms joined, so search can discard a non-match in one comparison.
     */
    public function __construct(
        public string $key,
        public string $vendor,
        public string $name,
        public string $label,
        public string $provider,
        public string $reference,
        public ?string $path = null,
        public array $aliases = [],
        public array $useCases = [],
        public array $terms = [],
        public string $searchText = '',
    ) {}

    /**
     * Rebuilds an icon from its toArray() form, which is how the cache file stores it.
     *
     * @param  array<string, mixed>  $record
     */
    public static function fromArray(array $record): self
    {
        return new self(
            key: $record['key'],
            vendor: $record['vendor'],
            name: $record['name'],
            label: $record['label'],
            provider: $record['provider'],
            reference: $record['reference'],
            path: $record['path'],
            aliases: $record['aliases'],
            useCases: $record['use_cases'],
            terms: $record['terms'],
            searchText: $record['search_text'],
        );
    }

    /**
     * @return array{key: string, vendor: string, name: string, label: string, provider: string, reference: string, path: string|null, aliases: list<string>, use_cases: list<string>, terms: array<string, int>, search_text: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'vendor' => $this->vendor,
            'name' => $this->name,
            'label' => $this->label,
            'provider' => $this->provider,
            'reference' => $this->reference,
            'path' => $this->path,
            'aliases' => $this->aliases,
            'use_cases' => $this->useCases,
            'terms' => $this->terms,
            'search_text' => $this->searchText,
        ];
    }

    /**
     * The search terms are an index for the registry, not part of the icon's description.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();
        unset($data['terms'], $data['search_text']);

        return $data;
    }
}
