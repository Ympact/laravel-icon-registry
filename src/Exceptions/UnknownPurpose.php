<?php

namespace Ympact\IconRegistry\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an icon is requested for a purpose the application has not defined properly.
 */
class UnknownPurpose extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No icon purpose named [{$name}]. Define it in the purposes file.");
    }

    public static function malformed(string $name): self
    {
        return new self("Icon purpose [{$name}] needs both an `icon` and a `label` in the purposes file.");
    }
}
