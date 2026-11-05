<?php

namespace Ympact\IconRegistry\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\ComponentAttributeBag;
use Ympact\IconRegistry\DiscoveredIcon;
use Ympact\IconRegistry\Icon;

/**
 * A source of icons: it lists the icons an application can draw and draws one by its reference.
 * Implementations are named in the `providers` config and receive the rest of their entry as options.
 */
interface IconProvider
{
    /**
     * Whether the package behind this provider is installed; an unavailable provider is skipped.
     */
    public function isAvailable(): bool;

    /**
     * @return iterable<DiscoveredIcon>
     */
    public function discover(): iterable;

    public function render(Icon $icon, ComponentAttributeBag $attributes): Htmlable;
}
