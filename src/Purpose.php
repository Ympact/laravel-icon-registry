<?php

namespace Ympact\IconRegistry;

use InvalidArgumentException;

/**
 * What an icon means in this application, such as "action.delete": the one icon that stands for
 * it and the label that says so, which is what keeps similar actions looking the same.
 */
final readonly class Purpose
{
    /**
     * @param  string  $icon  The key of the icon drawn for this purpose.
     * @param  string  $label  The accessible name, or the translation key for it.
     */
    public function __construct(
        public string $name,
        public string $icon,
        public string $label,
    ) {}

    /**
     * @throws InvalidArgumentException when the entry lacks an icon or a label.
     */
    public static function fromEntry(string $name, mixed $entry): self
    {
        $icon = is_array($entry) ? ($entry['icon'] ?? null) : null;
        $label = is_array($entry) ? ($entry['label'] ?? null) : null;

        if (! is_string($icon) || ! is_string($label) || blank($icon) || blank($label)) {
            throw new InvalidArgumentException("Icon purpose [{$name}] needs both an `icon` and a `label`.");
        }

        return new self($name, $icon, $label);
    }
}
