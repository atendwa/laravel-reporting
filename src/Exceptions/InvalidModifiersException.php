<?php

declare(strict_types=1);

namespace Reporting\Exceptions;

use RuntimeException;

final class InvalidModifiersException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $modifiers
     */
    public static function forModifiers(array $modifiers): self
    {
        $keys = implode(', ', array_keys($modifiers));

        return new self('Invalid modifiers provided: [' . $keys . '].');
    }
}
