<?php

declare(strict_types=1);

use Reporting\Generator\TestGenerator;
use Reporting\Services\CsvGenerator;

it('generates a csv file with headers and data', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate([]);
    $path = sys_get_temp_dir() . '/test_csv_' . uniqid() . '.csv';

    try {
        $csvGenerator = new CsvGenerator;
        $csvGenerator->generate($generator, $data, $path);

        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('ID');
        expect($content)->toContain('Name');
        expect($content)->toContain('Category');
        expect($content)->toContain('Item A');
        expect($content)->toContain('Item B');
        expect($content)->toContain('Item C');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates csv with filtered data', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate(['category' => 'Alpha']);
    $path = sys_get_temp_dir() . '/test_csv_filtered_' . uniqid() . '.csv';

    try {
        $csvGenerator = new CsvGenerator;
        $csvGenerator->generate($generator, $data, $path);

        $content = file_get_contents($path);
        expect($content)->toContain('Item A');
        expect($content)->toContain('Item C');
        expect($content)->not->toContain('Item B');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates csv with empty data', function (): void {
    $generator = new TestGenerator;
    $path = sys_get_temp_dir() . '/test_csv_empty_' . uniqid() . '.csv';

    try {
        $csvGenerator = new CsvGenerator;
        $csvGenerator->generate($generator, collect(), $path);

        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->not->toContain('Item A');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('writes a cross-tab CSV with the totals first and a column per group', function (): void {
    $path = sys_get_temp_dir() . '/test_grouped_' . uniqid() . '.csv';
    $data = collect([
        ['outlet' => 'Main', 'month' => 'Jan', 'region' => 'North', 'total' => 100],
        ['outlet' => 'Main', 'month' => 'Feb', 'region' => 'North', 'total' => 50],
        ['outlet' => 'Kiosk', 'month' => 'Jan', 'region' => 'South', 'total' => 25],
    ]);

    try {
        (new CsvGenerator)->generate(new TestGenerator, $data, $path, ['group_rows_by' => 'outlet', 'group_columns_by' => 'month', 'group_value_column' => 'total']);

        expect(array_map(str_getcsv(...), array_filter(explode("\n", str_replace("\u{FEFF}", '', (string) file_get_contents($path))))))->toBe([
            ['Outlet', 'Region', 'Feb', 'Jan'],
            ['Totals', '', '50', '125'],
            ['Main', 'North', '50', '100'],
            ['Kiosk', 'South', '0', '25'],
        ]);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('keeps numeric group names as column headers', function (): void {
    $path = sys_get_temp_dir() . '/test_grouped_years_' . uniqid() . '.csv';
    $data = collect([
        ['outlet' => 'Main', 'year' => 2025, 'total' => 10],
        ['outlet' => 'Main', 'year' => 2026, 'total' => 20],
    ]);

    try {
        (new CsvGenerator)->generate(new TestGenerator, $data, $path, ['group_rows_by' => 'outlet', 'group_columns_by' => 'year', 'group_value_column' => 'total']);

        expect(str_getcsv(explode("\n", str_replace("\u{FEFF}", '', (string) file_get_contents($path)))[0]))->toBe(['Outlet', '2025', '2026']);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('fills an all-capitals column such as ID from its lower-case row key', function (): void {
    $generator = new TestGenerator;
    $path = sys_get_temp_dir() . '/test_csv_ids_' . uniqid() . '.csv';

    try {
        (new CsvGenerator)->generate($generator, $generator->generate([]), $path);

        $rows = array_map(str_getcsv(...), array_slice(file($path, FILE_IGNORE_NEW_LINES), 1));

        expect(array_map(fn (array $row): string => mb_ltrim($row[0], "\u{FEFF}"), $rows))->toBe(['1', '2', '3']);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
