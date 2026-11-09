<?php

namespace Ympact\IconRegistry\Console;

use Illuminate\Console\Command;
use Ympact\IconRegistry\IconRegistry;

/**
 * Writes the JSON manifest editor extensions complete icon keys from.
 */
class ExportCommand extends Command
{
    protected $signature = 'icon-registry:export
        {--path= : Write the manifest here instead of the configured path}';

    protected $description = 'Write the icon manifest for editor extensions';

    public function handle(IconRegistry $registry): int
    {
        $path = $this->option('path') ?: $registry->editorManifestPath();

        $count = $registry->writeEditorManifest($path);

        $this->components->info("Exported {$count} icons to {$path}.");

        return self::SUCCESS;
    }
}
