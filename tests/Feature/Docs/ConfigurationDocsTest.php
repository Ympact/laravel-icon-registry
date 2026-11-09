<?php

use Illuminate\Support\Arr;

/**
 * Every key the published config can set appears in the configuration page, so an option added to
 * config/icon-registry.php cannot ship undocumented.
 */
it('documents every configuration key', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 3).'/docs/getting-started/configuration.md');
    $keys = array_keys(Arr::dot(require dirname(__DIR__, 3).'/config/icon-registry.php'));

    expect($keys)->not->toBeEmpty();

    foreach ($keys as $key) {
        // A provider's driver is documented once, for every provider.
        $documented = str_contains($page, "`{$key}`")
            || str_contains($page, '`'.preg_replace('/^providers\.[^.]+\./', 'providers.*.', $key).'`');

        expect($documented)->toBeTrue("Document `{$key}` in docs/getting-started/configuration.md.");
    }
});
