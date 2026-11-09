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
     * @param  list<string>  $purposes  The application purposes this icon stands for.
     * @param  array<string, int>  $terms  Lower-cased search terms, each weighted by where it came from.
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
        public array $purposes = [],
        public array $terms = [],
    ) {}

    /**
     * Rebuilds an icon from var_export() output, which is how the cache file stores it.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    /**
     * @return array{key: string, vendor: string, name: string, label: string, provider: string, reference: string, path: string|null, aliases: list<string>, purposes: list<string>, terms: array<string, int>}
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
            'purposes' => $this->purposes,
            'terms' => $this->terms,
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
        unset($data['terms']);

        return $data;
    }
}
