<?php

namespace Ympact\IconRegistry\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an icon key is drawn that no configured provider lists.
 */
class UnknownIcon extends InvalidArgumentException
{
    public static function key(string $key): self
    {
        return new self("No icon provider lists the icon [{$key}]. Validate stored keys with the IconExists rule.");
    }
}
