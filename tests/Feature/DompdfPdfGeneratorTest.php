<?php

declare(strict_types=1);

use Reporting\Contracts\PdfGeneratorContract;
use Reporting\Generator\TestGenerator;
use Reporting\Services\DompdfPdfGenerator;

it('implements the pdf generator contract', function (): void {
    $generator = new DompdfPdfGenerator;

    expect($generator)->toBeInstanceOf(PdfGeneratorContract::class);
});

it('generates a valid pdf file', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate([]);
    $path = sys_get_temp_dir() . '/test_dompdf_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, [], $path);

        expect(file_exists($path))->toBeTrue();
        expect(filesize($path))->toBeGreaterThan(0);
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates pdf with modifiers', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate(['category' => 'Alpha']);
    $path = sys_get_temp_dir() . '/test_dompdf_mods_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, ['category' => 'Alpha'], $path);

        expect(file_exists($path))->toBeTrue();
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates pdf with empty data', function (): void {
    $generator = new TestGenerator;
    $path = sys_get_temp_dir() . '/test_dompdf_empty_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, collect(), [], $path);

        expect(file_exists($path))->toBeTrue();
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('handles data exceeding chunk size by merging', function (): void {
    config(['reporting.pdf_chunk_size' => 5]);

    $generator = new TestGenerator;
    $data = collect(range(1, 25))->map(fn (int $i): array => [
        'id' => $i,
        'name' => 'Item ' . $i,
        'category' => 'Alpha',
        'amount' => $i * 10.50,
        'date' => '2026-01-01',
    ]);

    $path = sys_get_temp_dir() . '/test_dompdf_chunked_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, [], $path);

        expect(file_exists($path))->toBeTrue();
        expect(filesize($path))->toBeGreaterThan(0);
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates pdf in portrait orientation', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate([]);
    $path = sys_get_temp_dir() . '/test_dompdf_portrait_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, [], $path, 'portrait');

        expect(file_exists($path))->toBeTrue();
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('generates chunked pdf in portrait orientation', function (): void {
    config(['reporting.pdf_chunk_size' => 5]);

    $generator = new TestGenerator;
    $data = collect(range(1, 15))->map(fn (int $i): array => [
        'id' => $i,
        'name' => 'Item ' . $i,
        'category' => 'Alpha',
        'amount' => $i * 10.50,
        'date' => '2026-01-01',
    ]);

    $path = sys_get_temp_dir() . '/test_dompdf_portrait_chunked_' . uniqid() . '.pdf';

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, [], $path, 'portrait');

        expect(file_exists($path))->toBeTrue();
        expect(filesize($path))->toBeGreaterThan(0);
        expect(str_starts_with(file_get_contents($path), '%PDF'))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('cleans up temp files after merging', function (): void {
    config(['reporting.pdf_chunk_size' => 3]);

    $generator = new TestGenerator;
    $data = $generator->generate([]);
    $path = sys_get_temp_dir() . '/test_dompdf_cleanup_' . uniqid() . '.pdf';

    $tempFilesBefore = glob(sys_get_temp_dir() . '/rpt_chunk_*');

    try {
        $pdfGenerator = new DompdfPdfGenerator;
        $pdfGenerator->generate($generator, $data, [], $path);

        $tempFilesAfter = glob(sys_get_temp_dir() . '/rpt_chunk_*');
        expect($tempFilesAfter)->toEqual($tempFilesBefore);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
