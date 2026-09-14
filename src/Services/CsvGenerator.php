<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Reporting\Contracts\GeneratorInterface;
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
        $crossTabTransformer = new CrossTabTransformer;

        $pivoted = $crossTabTransformer->pivot(
            $data,
            $groupingConfig['group_rows_by'],
            $groupingConfig['group_columns_by'],
            $groupingConfig['group_value_column'],
        );

        $rowLabel = Str::headline($groupingConfig['group_rows_by']);
        $staticColumns = $pivoted['static_columns'];
        $columnGroups = $pivoted['column_groups'];

        $simpleExcelWriter = SimpleExcelWriter::create($fullPath);

        $aggregateRow = [$rowLabel => 'Totals'];

        foreach ($staticColumns as $col) {
            $aggregateRow[Str::headline($col)] = '';
        }

        foreach ($columnGroups as $colGroup) {
            $aggregateRow[(string) $colGroup] = $pivoted['aggregates'][$colGroup] ?? 0;
        }

        $simpleExcelWriter->addRow($aggregateRow);

        foreach ($pivoted['rows'] as $rowKey => $row) {
            $csvRow = [$rowLabel => $rowKey];

            foreach ($staticColumns as $staticColumn) {
                $csvRow[Str::headline($staticColumn)] = $row[$staticColumn] ?? '';
            }

            foreach ($columnGroups as $columnGroup) {
                $csvRow[(string) $columnGroup] = $row[$columnGroup] ?? 0;
            }

            $simpleExcelWriter->addRow($csvRow);
        }

        $simpleExcelWriter->close();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $headers
     *
     * @return array<string, mixed>
     */
    private function mapRowToHeaders(array $row, array $headers): array
    {
        $mapped = [];

        foreach ($headers as $header) {
            $mapped[$header] = $row[Str::snake($header)] ?? $row[$header] ?? '';
        }

        return $mapped;
    }
}
