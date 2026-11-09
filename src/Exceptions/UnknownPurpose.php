<?php

namespace Ympact\IconRegistry\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an icon is requested for a purpose the application has not defined.
 */
class UnknownPurpose extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No icon purpose named [{$name}]. Define it in the purposes file.");
    }
}
