<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Ympact\IconRegistry\IconRegistry;

/**
 * Reports the providers, the metadata and the cache, and optionally repairs what it can. It fails
 * on metadata or cache entries for icons that no longer exist, so it can guard CI.
 */
class DoctorCommand extends Command
{
    protected $signature = 'icon-registry:doctor
        {--fix : Prune stale metadata and rebuild the icon cache}
        {--prune-meta : Remove metadata entries for icons that no longer exist}
        {--clear-cache : Delete the icon registry cache}
        {--rebuild-cache : Rebuild the icon registry cache}';

    protected $description = 'Validate icon providers, metadata and cache';

    public function handle(IconRegistry $registry): int
    {
        $report = $registry->validate();

        $this->components->twoColumnDetail('Providers', implode(', ', array_keys($registry->providers())) ?: 'none available');
        $this->components->info("Found {$report['icon_count']} icons.");
        $this->components->info("Found {$report['metadata_count']} metadata entries.");

        match (true) {
            ! $report['cache_exists'] => $this->components->warn('Icon cache does not exist.'),
            ! $report['cache_is_fresh'] => $this->components->warn('Icon cache is stale.'),
            default => $this->components->info('Icon cache is fresh.'),
        };

        $this->listKeys('Metadata exists for missing icons:', $report['orphaned_metadata']);
        $this->listKeys('Cache contains icons that no longer exist:', $report['stale_cache_entries']);
        $this->listKeys('Listed by more than one provider (the first configured wins):', $report['shadowed_icons']);

        if ($report['icons_without_metadata'] !== []) {
            $this->components->twoColumnDetail('Icons without metadata', (string) count($report['icons_without_metadata']));
        }

        $this->repair($registry);

        return $report['orphaned_metadata'] !== [] || $report['stale_cache_entries'] !== []
            ? self::FAILURE
            : self::SUCCESS;
    }

    protected function repair(IconRegistry $registry): void
    {
        $fix = (bool) $this->option('fix');

        if ($fix || $this->option('prune-meta')) {
            $result = $registry->cleanMetadata(write: true);

            $this->components->info("Pruned {$result['removed_count']} stale metadata entries.");
        }

        if ($fix || $this->option('clear-cache')) {
            $this->components->info($registry->clearCache() ? 'Deleted icon cache.' : 'No icon cache to delete.');
        }

        if ($fix || $this->option('rebuild-cache')) {
            $this->components->info("Rebuilt icon cache with {$registry->rebuildCache()} icons.");
        }
    }

    /**
     * @param  list<string>  $keys
     */
    protected function listKeys(string $heading, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $this->components->warn($heading);

        foreach ($keys as $key) {
            $this->line("  - {$key}");
        }
    }
}
