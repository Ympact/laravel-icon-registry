<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Ympact\IconRegistry\IconRegistry;

/**
 * Deletes the icon registry cache, which `php artisan optimize:clear` also does.
 */
class ClearCommand extends Command
{
    protected $signature = 'icon-registry:clear';

    protected $description = 'Delete the icon registry cache';

    public function handle(IconRegistry $registry): int
    {
        $deleted = $registry->clearCache();

        $this->components->info($deleted ? 'Icon cache deleted.' : 'No icon cache existed.');

        return self::SUCCESS;
    }
}
