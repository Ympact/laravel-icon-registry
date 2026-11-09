<?php

use Ympact\IconRegistry\Facades\Icons;
use Ympact\IconRegistry\IconRegistry;

/**
 * IDEs only know the facade through its @method tags, so a public registry method without one is
 * invisible to the people using it.
 */
it('declares every public registry method on the facade', function (): void {
    $docblock = (string) (new ReflectionClass(Icons::class))->getDocComment();
    $methods = (new ReflectionClass(IconRegistry::class))->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if ($method->isConstructor()) {
            continue;
        }

        expect(preg_match('/@method static .* '.$method->getName().'\(/', $docblock))
            ->toBe(1, "Add a @method tag for {$method->getName()}() to Facades/Icons.php.");
    }
});
