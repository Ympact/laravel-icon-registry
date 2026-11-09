<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Ympact\IconRegistry\IconRegistry;

/**
 * Writes the icon registry cache, which `php artisan optimize` also does.
 */
class CacheCommand extends Command
{
    protected $signature = 'icon-registry:cache';

    protected $description = 'Rebuild the icon registry cache';

    public function handle(IconRegistry $registry): int
    {
        $count = $registry->rebuildCache();

        $this->components->info("Cached {$count} icons.");

        return self::SUCCESS;
    }
}
