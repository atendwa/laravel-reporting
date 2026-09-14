<?php

declare(strict_types=1);

namespace Reporting\Utilities;

final readonly class NumericFormatter
{
    /**
     * Format a value for PDF display.
     *
     * Integers get thousands separators, floats get 2 decimal places,
     * non-numeric values pass through unchanged.
     */
    public function format(mixed $value): string
    {
        if (! $this->isNumeric($value)) {
            return (string) $value;
        }

        $numeric = (float) $value;

        if ((float) (int) $numeric === $numeric && ! str_contains((string) $value, '.')) {
            return number_format((int) $numeric);
        }

        return number_format((float) $value, 2);
    }

    public function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }
}
