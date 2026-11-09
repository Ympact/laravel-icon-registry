<?php

namespace Ympact\IconRegistry\Actions;

use Illuminate\Support\Str;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconUsageReport;

/**
 * Adds the context keywords of a usage scan to the metadata file as use cases. It only ever
 * appends: labels, aliases, existing use cases and custom fields stay as they are.
 */
class AddIconUseCases
{
    /** Few enough that a curated entry is not swamped by words from one scan. */
    protected const MAXIMUM_PER_ICON = 5;

    public function __construct(protected IconRegistry $registry) {}

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
            $contextual = [];

            foreach ($icon['usages'] as $usage) {
                $contextual += array_filter($usage->keywords, fn (int $weight) => $weight >= ScanIconUsages::WEIGHT_CONTEXT);
            }

            $strongest = array_slice(array_values(array_filter($icon['keywords'], fn (string $word) => isset($contextual[$word]))), 0, self::MAXIMUM_PER_ICON);
            $new = array_values(array_diff($strongest, $this->wordsIn($metadata[$key] ?? [])));

            if ($new !== []) {
                $additions[$key] = $new;
            }
        }

        ksort($additions);

        return $additions;
    }

    /**
     * Rereads the file, so an edit made while the preview was on screen is kept.
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

            $metadata[$key] = $file->normalizeEntry($entry);
        }

        ksort($metadata);

        $file->write($metadata);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    protected function wordsIn(array $entry): array
    {
        $phrases = [(string) ($entry['label'] ?? ''), ...($entry['aliases'] ?? []), ...($entry['use_cases'] ?? [])];

        return preg_split('/[\s._-]+/', Str::lower(implode(' ', $phrases)), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
