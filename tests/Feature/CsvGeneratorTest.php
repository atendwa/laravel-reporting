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
