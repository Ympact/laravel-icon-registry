<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Ympact\IconRegistry\Icon;
use Ympact\IconRegistry\IconRegistry;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Searches the registry from the terminal: interactively, as a table for a given query, or as JSON.
 */
class SearchCommand extends Command
{
    /** How many aliases a prompt option shows after the label. */
    protected const PROMPT_ALIASES = 4;

    protected $signature = 'icon-registry:search
        {query? : Search query}
        {--vendor= : Limit results to a vendor, e.g. flux, app, tabler}
        {--provider= : Limit results to a provider, e.g. flux, blade-icons}
        {--limit=50 : Maximum number of results}
        {--fresh : Ignore the cache and ask the providers directly}
        {--json : Output JSON instead of the interactive UI}
        {--key-only : Only output the selected icon key}';

    protected $description = 'Search the icons the application can draw';

    public function handle(IconRegistry $registry): int
    {
        $query = $this->argument('query');

        if ($this->option('json')) {
            $this->line((string) json_encode($this->results($registry, $query)->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($query) {
            return $this->renderTable($registry, $query);
        }

        return $this->renderInteractive($registry);
    }

    /**
     * @return Collection<int, Icon>
     */
    protected function results(IconRegistry $registry, ?string $query): Collection
    {
        return $registry->search(
            query: $query,
            vendor: $this->option('vendor'),
            limit: (int) $this->option('limit'),
            fresh: (bool) $this->option('fresh'),
            provider: $this->option('provider'),
        );
    }

    protected function renderInteractive(IconRegistry $registry): int
    {
        $vendor = $this->option('vendor');

        $selected = search(
            label: $vendor ? "Search {$vendor} icons" : 'Search icons',
            options: fn (string $value) => $value === ''
                ? []
                : $this->results($registry, $value)->mapWithKeys(fn (Icon $icon) => [$icon->key => $this->promptLabel($icon)])->all(),
            placeholder: 'Type an icon name, alias or use case...',
            hint: 'Searches icon key, vendor, label, aliases and use cases.',
        );

        $icon = $registry->find((string) $selected, (bool) $this->option('fresh'));

        if ($icon === null) {
            warning("Icon [{$selected}] was selected but could not be found.");

            return self::FAILURE;
        }

        if ($this->option('key-only')) {
            $this->line($icon->key);

            return self::SUCCESS;
        }

        info($icon->key);

        table(['Field', 'Value'], [
            ['Key', $icon->key],
            ['Vendor', $icon->vendor],
            ['Name', $icon->name],
            ['Label', $icon->label],
            ['Provider', $icon->provider],
            ['Reference', $icon->reference],
            ['Path', $icon->path ?? ''],
            ['Aliases', implode(', ', $icon->aliases)],
            ['Use cases', implode(', ', $icon->useCases)],
            ['Purposes', implode(', ', $icon->purposes)],
        ]);

        note('Store the registry key, not the reference.');

        return self::SUCCESS;
    }

    protected function renderTable(IconRegistry $registry, string $query): int
    {
        $results = $this->results($registry, $query);

        if ($results->isEmpty()) {
            warning('No icons found.');

            return self::FAILURE;
        }

        table(
            ['Key', 'Provider', 'Label', 'Aliases', 'Use cases', 'Purposes'],
            $results->map(fn (Icon $icon) => [
                $icon->key,
                $icon->provider,
                $icon->label,
                implode(', ', $icon->aliases),
                implode(', ', $icon->useCases),
                implode(', ', $icon->purposes),
            ])->all()
        );

        note("Showing {$results->count()} result(s).");

        return self::SUCCESS;
    }

    protected function promptLabel(Icon $icon): string
    {
        $label = "{$icon->label} ({$icon->key})";

        if ($icon->aliases === []) {
            return $label;
        }

        return $label.': '.implode(', ', array_slice($icon->aliases, 0, self::PROMPT_ALIASES));
    }
}
