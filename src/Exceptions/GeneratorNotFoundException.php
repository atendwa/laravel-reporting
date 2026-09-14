<?php

declare(strict_types=1);

namespace Reporting\Exceptions;

use RuntimeException;

final class GeneratorNotFoundException extends RuntimeException
{
    public static function forClass(string $class): self
    {
        return new self('Report generator [' . $class . '] not found.');
    }
}
