<?php

namespace Ympact\IconRegistry\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconSearch;
use Ympact\IconRegistry\IconUsage;
use Ympact\IconRegistry\IconUsageReport;

/**
 * Adds the context keywords of a usage scan to the metadata file as use cases. It only ever
 * appends: labels, aliases, existing use cases and custom fields stay as they are.
 */
class AddIconUseCases
{
    /** Few enough that a curated entry is not swamped by words from one scan. */
    protected const MAXIMUM_PER_ICON = 5;

    public function __construct(
        protected IconRegistry $registry,
        protected IconSearch $search,
    ) {}

    /**
     * The use cases a write would add, by icon key, leaving out what the entry already says.
     * The strongest keywords are chosen before that, so a repeat scan finds nothing new to add.
     *
     * @return array<string, list<string>>
     */
    public function additionsFrom(IconUsageReport $report): array
    {
        $metadata = $this->registry->metadata()->read();
        $additions = [];

        foreach ($report->icons() as $key => $icon) {
            $contextual = array_merge(...array_map(fn (IconUsage $usage) => $usage->contextKeywords, $icon['usages']));
            $strongest = array_slice(array_values(array_intersect($icon['keywords'], $contextual)), 0, self::MAXIMUM_PER_ICON);
            $new = array_values(array_diff($strongest, $this->wordsIn($metadata[$key] ?? [])));

            if ($new !== []) {
                $additions[$key] = $new;
            }
        }

        ksort($additions);

        return $additions;
    }

    /**
     * Rereads the file, so an edit made while the preview was on screen is kept. Existing entries
     * keep their order, so a hand-kept section stays under its banner.
     *
     * @param  array<string, list<string>>  $additions
     */
    public function handle(array $additions): void
    {
        $file = $this->registry->metadata();
        $metadata = $file->read();

        foreach ($additions as $key => $useCases) {
            $entry = $metadata[$key] ?? [];
            $entry['use_cases'] = [...array_values($entry['use_cases'] ?? []), ...$useCases];

            $isNew = ! isset($metadata[$key]);
            $entry = $file->normalizeEntry($entry);

            if ($isNew) {
                $metadata = $this->insertedByVendor($metadata, $key, $entry);
            } else {
                $metadata[$key] = $entry;
            }
        }

        $file->write($metadata);
    }

    /**
     * Places an entry after the last one of its vendor. A vendor new to the file goes first,
     * above any banner, because anywhere else it would read as part of another section.
     *
     * @param  array<string, array<string, mixed>>  $metadata
     * @param  array<string, mixed>  $entry
     * @return array<string, array<string, mixed>>
     */
    protected function insertedByVendor(array $metadata, string $key, array $entry): array
    {
        $vendor = Str::before($key, '.');
        $sameVendor = array_keys(array_filter(array_keys($metadata), fn (string $existing) => Str::before($existing, '.') === $vendor));
        $position = $sameVendor === [] ? 0 : Arr::last($sameVendor) + 1;

        return array_slice($metadata, 0, $position, true) + [$key => $entry] + array_slice($metadata, $position, null, true);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    protected function wordsIn(array $entry): array
    {
        $phrases = [(string) ($entry['label'] ?? ''), ...($entry['aliases'] ?? []), ...($entry['use_cases'] ?? [])];

        return $this->search->tokens(Str::lower(implode(' ', $phrases)));
    }
}
