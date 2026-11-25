<?php

declare(strict_types=1);

namespace Reporting\Support;

use Illuminate\Support\Str;

final class RowValue
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function for(array $row, string $header): mixed
    {
        return $row[Str::snake($header)] ?? $row[Str::snake(Str::lower($header))] ?? $row[$header] ?? '';
    }
}
