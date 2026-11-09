<?php

namespace Ympact\IconRegistry;

use JsonSerializable;

/**
 * What a source scan found: icons that resolved, grouped by key, and the references that did not.
 */
final readonly class IconUsageReport implements JsonSerializable
{
    /**
     * @param  list<IconUsage>  $usages
     */
    public function __construct(public array $usages) {}

    /**
     * The icons in use, by key, with their context keywords strongest first.
     *
     * @return array<string, array{keywords: list<string>, usages: list<IconUsage>}>
     */
    public function icons(): array
    {
        $grouped = [];

        foreach ($this->usages as $usage) {
            if ($usage->key !== null) {
                $grouped[$usage->key][] = $usage;
            }
        }

        ksort($grouped);

        return array_map(fn (array $usages) => ['keywords' => $this->rankedKeywords($usages), 'usages' => $usages], $grouped);
    }

    /**
     * Literal references that are unmistakably icons but that no provider lists.
     *
     * @return list<IconUsage>
     */
    public function unknown(): array
    {
        return array_values(array_filter($this->usages, fn (IconUsage $usage) => $usage->key === null && ! $usage->dynamic));
    }

    /**
     * References whose icon is only known at runtime.
     *
     * @return list<IconUsage>
     */
    public function dynamic(): array
    {
        return array_values(array_filter($this->usages, fn (IconUsage $usage) => $usage->dynamic));
    }

    /**
     * @return array{icons: array<string, array{keywords: list<string>, usages: list<IconUsage>}>, unknown: list<IconUsage>, dynamic: list<IconUsage>}
     */
    public function jsonSerialize(): array
    {
        return [
            'icons' => $this->icons(),
            'unknown' => $this->unknown(),
            'dynamic' => $this->dynamic(),
        ];
    }

    /**
     * @param  list<IconUsage>  $usages
     * @return list<string>
     */
    protected function rankedKeywords(array $usages): array
    {
        $scores = [];

        foreach ($usages as $usage) {
            foreach ($usage->keywords as $word => $weight) {
                $scores[$word] = ($scores[$word] ?? 0) + $weight;
            }
        }

        uksort($scores, fn (string $first, string $second) => [$scores[$second], $first] <=> [$scores[$first], $second]);

        return array_map(strval(...), array_keys($scores));
    }
}
