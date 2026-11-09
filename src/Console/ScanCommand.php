<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Ympact\IconRegistry\Actions\AddIconUseCases;
use Ympact\IconRegistry\Actions\ScanIconUsages;
use Ympact\IconRegistry\IconRegistry;
use Ympact\IconRegistry\IconUsage;
use Ympact\IconRegistry\IconUsageReport;

/**
 * Reports which icons the application's source refers to and what each is used for, and on
 * request adds those keywords to the metadata file as use cases.
 */
class ScanCommand extends Command
{
    /** How many keywords fit a table row; --json has them all. */
    protected const TABLE_KEYWORDS = 6;

    protected $signature = 'icon-registry:scan
        {--path=* : Directories to scan instead of resources/views, app and config}
        {--json : Output JSON instead of tables}
        {--write : Add the keywords to the metadata file as use cases, after a preview}
        {--force : With --write, skip the confirmation}';

    protected $description = 'Find the icons the application uses and keywords for their context';

    public function handle(ScanIconUsages $scan, AddIconUseCases $addUseCases, IconRegistry $registry): int
    {
        if ($this->option('json') && $this->option('write')) {
            $this->components->error('--json and --write cannot be combined: the preview needs the terminal.');

            return self::FAILURE;
        }

        /** @var list<string> $requested */
        $requested = $this->option('path');
        $directories = $requested === [] ? $this->defaultDirectories() : array_map($this->absolute(...), $requested);

        $missing = array_filter($directories, fn (string $directory) => ! is_dir($directory));

        if ($missing !== []) {
            $this->components->error('Not a directory: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $report = $scan->handle($directories);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderReport($report);

        if ($this->option('write')) {
            $this->writeUseCases($report, $addUseCases, $registry);
        }

        return self::SUCCESS;
    }

    protected function writeUseCases(IconUsageReport $report, AddIconUseCases $addUseCases, IconRegistry $registry): void
    {
        $additions = $addUseCases->additionsFrom($report);

        if ($additions === []) {
            $this->components->info('No new use cases to add.');

            return;
        }

        $this->table(['Icon', 'Use cases to add'], array_map(
            fn (string $key, array $useCases) => [$key, implode(', ', $useCases)],
            array_keys($additions),
            $additions,
        ));

        $path = $registry->metadata()->path();

        if (! $this->option('force') && ! $this->confirm("Add these to {$path}?")) {
            $this->components->info('Nothing was written.');

            return;
        }

        $addUseCases->handle($additions);

        $this->components->info('Added use cases to '.count($additions).' icons.');

        // The cache holds each icon's search terms, so search would not see the new use cases.
        if (is_file($registry->cachePath())) {
            $this->components->info("Rebuilt icon cache with {$registry->rebuildCache()} icons.");
        }
    }

    /**
     * @return list<string>
     */
    protected function defaultDirectories(): array
    {
        return array_values(array_filter([resource_path('views'), app_path(), config_path()], is_dir(...)));
    }

    protected function absolute(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    protected function renderReport(IconUsageReport $report): void
    {
        $icons = $report->icons();

        if ($icons === []) {
            $this->components->warn('No icon references found.');
        } else {
            $this->table(['Icon', 'Uses', 'Keywords', 'First used in'], array_map(
                fn (string $key, array $icon) => [
                    $key,
                    count($icon['usages']),
                    implode(', ', array_slice($icon['keywords'], 0, self::TABLE_KEYWORDS)),
                    $this->location($icon['usages'][0]),
                ],
                array_keys($icons),
                $icons,
            ));
        }

        $this->listUsages('Not listed by any provider:', $report->unknown());
        $this->listUsages('Chosen at runtime, so not resolved:', $report->dynamic());
    }

    /**
     * @param  list<IconUsage>  $usages
     */
    protected function listUsages(string $heading, array $usages): void
    {
        if ($usages === []) {
            return;
        }

        $this->components->warn($heading);

        foreach ($usages as $usage) {
            $this->line("  - {$usage->reference} ({$this->location($usage)})");
        }
    }

    protected function location(IconUsage $usage): string
    {
        return "{$usage->file}:{$usage->line}";
    }
}
