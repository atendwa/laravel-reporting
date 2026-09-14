<?php

declare(strict_types=1);

namespace Reporting\Utilities;

use Illuminate\Support\Collection;

final readonly class CrossTabTransformer
{
    /**
     * Pivot flat data into a cross-tab structure.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     *
     * @return array{
     *   column_groups: array<int, string>,
     *   aggregates: array<string, float>,
     *   rows: array<string, array<string, float|string>>,
     *   static_columns: array<int, string>,
     * }
     */
    public function pivot(Collection $data, string $groupRowsBy, string $groupColumnsBy, string $valueColumn): array
    {
        $columnGroups = $data
            ->pluck($groupColumnsBy)->unique()->sort()->values()
            ->map(fn (mixed $v): string => (string) $v)
            ->all();

        $staticColumns = $this->resolveStaticColumns($data, $groupRowsBy, $groupColumnsBy, $valueColumn);

        $grouped = $data->groupBy($groupRowsBy);

        $rows = [];

        foreach ($grouped as $rowKey => $items) {
            $row = $this->buildStaticValues($items->first(), $staticColumns);

            foreach ($columnGroups as $columnGroup) {
                $row[$columnGroup] = $items
                    ->where($groupColumnsBy, $columnGroup)
                    ->sum($valueColumn);
            }

            $rows[(string) $rowKey] = $row;
        }

        $aggregates = $this->computeAggregates($rows, $columnGroups);

        return [
            'static_columns' => $staticColumns,
            'column_groups' => $columnGroups,
            'aggregates' => $aggregates,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     *
     * @return array<int, string>
     */
    private function resolveStaticColumns(
        Collection $data,
        string $groupRowsBy,
        string $groupColumnsBy,
        string $valueColumn,
    ): array {
        $first = $data->first();

        if ($first === null) {
            return [];
        }

        $excluded = [$groupColumnsBy, $valueColumn, $groupRowsBy];

        return collect(array_keys((array) $first))
            ->reject(fn (string $key): bool => in_array($key, $excluded, strict: true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $staticColumns
     *
     * @return array<string, mixed>
     */
    private function buildStaticValues(array $row, array $staticColumns): array
    {
        $values = [];

        foreach ($staticColumns as $staticColumn) {
            $values[$staticColumn] = $row[$staticColumn] ?? '';
        }

        return $values;
    }

    /**
     * @param  array<string, array<string, float|string>>  $rows
     * @param  array<int, string>  $columnGroups
     *
     * @return array<string, float>
     */
    private function computeAggregates(array $rows, array $columnGroups): array
    {
        $aggregates = [];

        foreach ($columnGroups as $columnGroup) {
            $aggregates[$columnGroup] = 0.0;

            foreach ($rows as $row) {
                $aggregates[$columnGroup] += (float) ($row[$columnGroup] ?? 0);
            }
        }

        return $aggregates;
    }
}
