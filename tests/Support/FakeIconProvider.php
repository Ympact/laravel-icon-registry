<?php

namespace Tests\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Ympact\IconRegistry\Contracts\IconProvider;
use Ympact\IconRegistry\DiscoveredIcon;
use Ympact\IconRegistry\Icon;

/**
 * A provider with one icon, "fake.star", whose availability a test controls through its options.
 */
class FakeIconProvider implements IconProvider
{
    /**
     * @param  array{available?: bool}  $options
     */
    public function __construct(protected array $options = []) {}

    public function isAvailable(): bool
    {
        return $this->options['available'] ?? true;
    }

    public function discover(): array
    {
        return [new DiscoveredIcon('fake', 'star', 'star')];
    }

    public function render(Icon $icon, ComponentAttributeBag $attributes): Htmlable
    {
        return new HtmlString("<i data-fake=\"{$icon->reference}\"></i>");
    }
}
