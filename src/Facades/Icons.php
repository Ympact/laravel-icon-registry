<?php

namespace Ympact\IconRegistry\Facades;

use Illuminate\Support\Facades\Facade;
use Ympact\IconRegistry\IconRegistry;

/**
 * Static access to the icon registry for application code.
 *
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> all()
 * @method static \Ympact\IconRegistry\Icon|null find(string $key, bool $fresh = false)
 * @method static \Illuminate\Support\Collection<int, string> vendors()
 * @method static \Illuminate\Support\Collection<int, \Ympact\IconRegistry\Icon> search(?string $query = null, ?string $vendor = null, int $limit = 50, bool $fresh = false, ?string $provider = null)
 * @method static \Illuminate\Support\Collection<string, \Ympact\IconRegistry\Purpose> purposes()
 * @method static \Ympact\IconRegistry\Icon purpose(string $name)
 * @method static \Illuminate\Contracts\Support\Htmlable renderPurpose(string $name, \Illuminate\View\ComponentAttributeBag|array<string, mixed> $attributes = [])
 * @method static \Illuminate\Contracts\Support\Htmlable render(string $key, \Illuminate\View\ComponentAttributeBag|array<string, mixed> $attributes = [])
 *
 * @see IconRegistry
 */
class Icons extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IconRegistry::class;
    }
}
