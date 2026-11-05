<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Every command the package registers, and each of its arguments and options, appears in the
 * commands reference, so a new flag cannot ship undocumented.
 */
it('documents every command, argument and option', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 3).'/docs/reference/commands.md');
    $commands = collect(Artisan::all())->filter(fn ($command, string $name) => Str::startsWith($name, 'icon-registry:'));

    expect($commands)->not->toBeEmpty();

    foreach ($commands as $name => $command) {
        expect(str_contains($page, "## `{$name}`"))->toBeTrue("Add a section for `{$name}` to docs/reference/commands.md.");

        $definition = $command->getNativeDefinition();
        $section = Str::between($page, "## `{$name}`", '## `');

        foreach ($definition->getArguments() as $argument) {
            expect(str_contains($section, "`{$argument->getName()}`"))->toBeTrue("Document the `{$argument->getName()}` argument of `{$name}`.");
        }

        foreach ($definition->getOptions() as $option) {
            expect(str_contains($section, "`--{$option->getName()}"))->toBeTrue("Document the `--{$option->getName()}` option of `{$name}`.");
        }
    }
});
