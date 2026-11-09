<?php

namespace Ympact\IconRegistry;

use Illuminate\Support\Str;

/**
 * Scores icons against a query: every query token must match some term of an icon, and the icons
 * that survive are ranked by how well they matched. Typo tolerance is a fallback tier.
 */
class IconSearch
{
    /** Match quality, best first; a query token scores its best term. */
    protected const SCORE_EXACT = 100;

    protected const SCORE_PREFIX = 80;

    protected const SCORE_CONTAINS = 60;

    protected const SCORE_FUZZY = 50;

    /** Where a term came from, as a percentage applied to the match score. */
    protected const WEIGHT_NAME = 100;

    protected const WEIGHT_LABEL = 100;

    protected const WEIGHT_ALIAS = 80;

    protected const WEIGHT_VENDOR = 70;

    protected const WEIGHT_PURPOSE = 55;

    /** A word taken out of a longer phrase, as a percentage of the phrase's weight. */
    protected const WEIGHT_PHRASE_PART = 85;

    /** Tie-breakers for a token that is the icon's own name or vendor. */
    protected const BONUS_NAME = 25;

    protected const BONUS_VENDOR = 10;

    /**
     * The terms a query is matched against, flattened once at build time so a keystroke never
     * re-splits strings. A hit on the label or the icon's own name outweighs one on an alias,
     * and that one outweighs a purpose.
     *
     * @param  list<string>  $aliases
     * @param  list<string>  $purposes
     * @return array<string, int>
     */
    public function termsFor(
        string $key,
        string $vendor,
        string $name,
        string $label,
        array $aliases,
        array $purposes,
    ): array {
        $sources = [
            [$key, self::WEIGHT_NAME],
            [$name, self::WEIGHT_NAME],
            [$label, self::WEIGHT_LABEL],
            [$vendor, self::WEIGHT_VENDOR],
            ...array_map(fn (string $alias) => [$alias, self::WEIGHT_ALIAS], $aliases),
            ...array_map(fn (string $purpose) => [$purpose, self::WEIGHT_PURPOSE], $purposes),
        ];

        $terms = [];

        foreach ($sources as [$value, $weight]) {
            $value = Str::lower(trim($value));

            if ($value === '') {
                continue;
            }

            $parts = array_fill_keys($this->tokens($value), intdiv($weight * self::WEIGHT_PHRASE_PART, 100));

            foreach ([$value => $weight] + $parts as $term => $termWeight) {
                $terms[$term] = max($terms[$term] ?? 0, $termWeight);
            }
        }

        return $terms;
    }

    /**
     * The icons matching every token of the query, best first. An empty query returns the
     * icons in label order.
     *
     * @param  array<int, Icon>  $icons
     * @return list<Icon>
     */
    public function search(array $icons, ?string $query, int $limit): array
    {
        $tokens = $this->tokens(Str::lower(trim((string) $query)));

        if ($tokens === []) {
            usort($icons, fn (Icon $a, Icon $b) => strcmp($a->vendor, $b->vendor)
                ?: strcasecmp($a->label, $b->label)
                ?: strcmp($a->key, $b->key));

            return array_slice($icons, 0, $limit);
        }

        // A near-miss surfaces only when nothing matched outright, so it never outranks a real
        // hit, and the common query never pays for levenshtein() at all.
        $hits = $this->match($icons, $tokens, fuzzy: false) ?: $this->match($icons, $tokens, fuzzy: true);

        usort($hits, fn (array $a, array $b) => $b['score'] <=> $a['score']
            ?: strcasecmp($a['icon']->label, $b['icon']->label)
            ?: strcmp($a['icon']->key, $b['icon']->key));

        return array_column(array_slice($hits, 0, $limit), 'icon');
    }

    /**
     * A plain loop over plain arrays: this runs per keystroke over the whole set, and
     * Collection's closure-per-item overhead cost several times the matching itself.
     *
     * @param  array<int, Icon>  $icons
     * @param  list<string>  $tokens
     * @return list<array{icon: Icon, score: int}>
     */
    protected function match(array $icons, array $tokens, bool $fuzzy): array
    {
        $hits = [];

        foreach ($icons as $icon) {
            $score = $this->score($icon, $tokens, $fuzzy);

            if ($score !== null) {
                $hits[] = ['icon' => $icon, 'score' => $score];
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $value): array
    {
        $tokens = preg_split('/[\s._-]+/', $value, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($tokens));
    }

    /**
     * Null when a token matches nothing: all tokens must land, so "energy label" is not
     * satisfied by "energy" alone.
     *
     * @param  list<string>  $tokens
     */
    protected function score(Icon $icon, array $tokens, bool $fuzzy): ?int
    {
        $score = 0;

        foreach ($tokens as $token) {
            $best = 0;

            foreach ($icon->terms as $term => $weight) {
                $base = $this->scoreTerm((string) $term, $token, $fuzzy);

                if ($base === 0) {
                    continue;
                }

                $best = max($best, intdiv($base * $weight, 100));

                // A full-weight exact hit is the ceiling; nothing beats it.
                if ($best === self::SCORE_EXACT) {
                    break;
                }
            }

            if ($best === 0) {
                return null;
            }

            $score += $best;
        }

        // The name and vendor are what people type, so "tabler home" beats an icon merely aliased "home".
        if (in_array(Str::lower($icon->name), $tokens, true)) {
            $score += self::BONUS_NAME;
        }

        if (in_array(Str::lower($icon->vendor), $tokens, true)) {
            $score += self::BONUS_VENDOR;
        }

        return $score;
    }

    protected function scoreTerm(string $term, string $token, bool $fuzzy): int
    {
        if ($term === $token) {
            return self::SCORE_EXACT;
        }

        if (str_starts_with($term, $token)) {
            return self::SCORE_PREFIX;
        }

        if (str_contains($term, $token)) {
            return self::SCORE_CONTAINS;
        }

        if (! $fuzzy) {
            return 0;
        }

        $threshold = $this->fuzzyThreshold($token);

        // Bail before levenshtein() whenever the lengths alone rule a match out.
        if ($threshold === 0 || abs(strlen($term) - strlen($token)) > $threshold) {
            return 0;
        }

        $distance = $this->editDistance($token, $term);

        return $distance <= $threshold
            ? self::SCORE_FUZZY - ($distance * 10)
            : 0;
    }

    /**
     * levenshtein() charges two edits for a swapped pair, but a transposition is the commonest
     * typo there is: "trahs" for "trash" should not cost as much as two unrelated mistakes.
     */
    protected function editDistance(string $token, string $term): int
    {
        $distance = levenshtein($token, $term);

        if ($distance === 2 && $this->isTransposition($token, $term)) {
            return 1;
        }

        return $distance;
    }

    protected function isTransposition(string $token, string $term): bool
    {
        if (strlen($token) !== strlen($term)) {
            return false;
        }

        $differing = [];

        for ($i = 0, $length = strlen($token); $i < $length; $i++) {
            if ($token[$i] === $term[$i]) {
                continue;
            }

            $differing[] = $i;

            if (count($differing) > 2) {
                return false;
            }
        }

        return count($differing) === 2
            && $differing[1] === $differing[0] + 1
            && $token[$differing[0]] === $term[$differing[1]]
            && $token[$differing[1]] === $term[$differing[0]];
    }

    /**
     * How many edits to forgive. Under four characters none are: one edit on a three-letter
     * token reaches a large part of the set, and the results read as noise, not a correction.
     */
    protected function fuzzyThreshold(string $token): int
    {
        return match (true) {
            strlen($token) < 4 => 0,
            strlen($token) <= 5 => 1,
            strlen($token) <= 8 => 2,
            default => 3,
        };
    }
}
