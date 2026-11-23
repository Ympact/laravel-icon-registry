<?php

use Ympact\IconRegistry\Icon;

function iconWith(string $key, string $reference): Icon
{
    [$vendor, $name] = explode('.', $key, 2);

    return new Icon(key: $key, vendor: $vendor, name: $name, label: $name, provider: 'flux', reference: $reference);
}

it('is drawable by its key when the key is what its provider draws', function (): void {
    expect(iconWith('tabler.home', 'tabler.home')->isDrawableByKey())->toBeTrue();
});

it('is not drawable by its key when its provider draws it by another name', function (): void {
    expect(iconWith('flux.home', 'home')->isDrawableByKey())->toBeFalse()
        ->and(iconWith('heroicons.o-home', 'heroicon-o-home')->isDrawableByKey())->toBeFalse();
});
