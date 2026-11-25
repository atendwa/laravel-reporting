<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Support\RowValue;
use Reporting\Utilities\CrossTabTransformer;
use Spatie\SimpleExcel\SimpleExcelWriter;

final class CsvGenerator
{
    /**
     * Generate a CSV file at the given path from report data.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null  $groupingConfig
     */
    public function generate(
        GeneratorInterface $generator,
        Collection $data,
        string $fullPath,
        ?array $groupingConfig = null,
    ): void {
        if ($groupingConfig !== null && $data->isNotEmpty()) {
            $this->generateGrouped($data, $fullPath, $groupingConfig);

            return;
        }

        $this->generateFlat($generator, $data, $fullPath);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     */
    private function generateFlat(GeneratorInterface $generator, Collection $data, string $fullPath): void
    {
        $simpleExcelWriter = SimpleExcelWriter::create($fullPath);
        $headers = $generator->getHeaders();

        $data
            ->chunk((int) config('reporting.chunk_size', 1000))
            ->each(fn (Collection $chunk) => $chunk->each(
                fn (array $row): SimpleExcelWriter => $simpleExcelWriter->addRow($this->mapRowToHeaders($row, $headers))
            ));

        $simpleExcelWriter->close();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}  $groupingConfig
     */
    private function generateGrouped(Collection $data, string $fullPath, array $groupingConfig): void
    {
        $pivoted = (new CrossTabTransformer)->pivot($data, $groupingConfig['group_rows_by'], $groupingConfig['group_columns_by'], $groupingConfig['group_value_column']);
        $rowLabel = Str::headline($groupingConfig['group_rows_by']);
        $writer = SimpleExcelWriter::create($fullPath);

        $writer->addRow($this->crossTabRow($rowLabel, 'Totals', [], $pivoted['static_columns'], $pivoted['column_groups'], $pivoted['aggregates']));

        foreach ($pivoted['rows'] as $rowKey => $row) {
            $writer->addRow($this->crossTabRow($rowLabel, $rowKey, $row, $pivoted['static_columns'], $pivoted['column_groups'], $row));
        }

        $writer->close();
    }

    /**
     * @param  array<string|int, mixed>  $row
     * @param  array<int, string>  $staticColumns
     * @param  array<int, string|int>  $columnGroups
     * @param  array<string|int, mixed>  $values
     *
     * @return array<string, mixed>
     */
    private function crossTabRow(string $rowLabel, string|int $label, array $row, array $staticColumns, array $columnGroups, array $values): array
    {
        return [$rowLabel => $label]
            + collect($staticColumns)->mapWithKeys(fn (string $column): array => [Str::headline($column) => $row[$column] ?? ''])->all()
            + collect($columnGroups)->mapWithKeys(fn (string|int $group): array => [(string) $group => $values[$group] ?? 0])->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $headers
     *
     * @return array<string, mixed>
     */
    private function mapRowToHeaders(array $row, array $headers): array
    {
        return collect($headers)->mapWithKeys(fn (string $header): array => [$header => RowValue::for($row, $header)])->all();
    }
}
