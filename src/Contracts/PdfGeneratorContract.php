<?php

declare(strict_types=1);

namespace Reporting\Contracts;

use Illuminate\Support\Collection;

interface PdfGeneratorContract
{
    /**
     * Generate a PDF file at the given path from report data.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null  $groupingConfig
     */
    public function generate(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers,
        string $fullPath,
        string $orientation = 'landscape',
        ?array $groupingConfig = null,
    ): void;
}
