<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Contracts\PdfGeneratorContract;
use Reporting\Models\Report;

final readonly class FileGenerator
{
    public function __construct(
        private CsvGenerator $csvGenerator,
        private PdfGeneratorContract $pdfGeneratorContract,
    ) {}

    /**
     * Generate report files based on the report's output format toggles.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     *
     * @return array{csv_path: ?string, pdf_path: ?string, file_size: int, row_count: int}
     */
    public function generate(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers = [],
        ?Report $report = null,
    ): array {
        $generateCsv = $report?->generate_csv ?? true;
        $generatePdf = $report?->generate_pdf ?? false;

        $baseName = $this->buildFileName($generator);
        $orientation = $report?->orientation ?? 'landscape';
        $groupingConfig = $this->resolveGroupingConfig($report);

        $csvPath = $generateCsv ? $this->buildCsv($generator, $data, $baseName, $groupingConfig) : null;
        $pdfPath = $generatePdf
            ? $this->buildPdf($generator, $data, $modifiers, $baseName, $orientation, $groupingConfig)
            : null;
        $fileSize = $this->calculateFileSize($csvPath, $pdfPath);

        return [
            'row_count' => $data->count(),
            'file_size' => $fileSize,
            'csv_path' => $csvPath,
            'pdf_path' => $pdfPath,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null  $groupingConfig
     */
    private function buildCsv(
        GeneratorInterface $generator,
        Collection $data,
        string $baseName,
        ?array $groupingConfig
    ): string {
        $relativePath = $this->directory() . '/' . $baseName . '.csv';

        $this->ensureDirectoryExists();
        $this->csvGenerator->generate($generator, $data, $this->fullPath($relativePath), $groupingConfig);

        return $relativePath;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null  $groupingConfig
     */
    private function buildPdf(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers,
        string $baseName,
        string $orientation,
        ?array $groupingConfig,
    ): string {
        $relativePath = $this->directory() . '/' . $baseName . '.pdf';

        $this->ensureDirectoryExists();
        $this->pdfGeneratorContract->generate(
            $generator, $data, $modifiers, $this->fullPath($relativePath), $orientation, $groupingConfig
        );

        return $relativePath;
    }

    /**
     * @return array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null
     */
    private function resolveGroupingConfig(?Report $report): ?array
    {
        if (! $report instanceof Report || ! $report->isGrouped()) {
            return null;
        }

        return [
            'group_rows_by' => $report->group_rows_by,
            'group_columns_by' => $report->group_columns_by,
            'group_value_column' => $report->group_value_column ?? 'total_amount',
        ];
    }

    private function buildFileName(GeneratorInterface $generator): string
    {
        $slug = Str::slug($generator->getName());
        $timestamp = now()->format('Y-m-d_His');

        return sprintf('%s_%s_', $slug, $timestamp) . Str::random(6);
    }

    private function calculateFileSize(?string $csvPath, ?string $pdfPath): int
    {
        $disk = $this->disk();

        return $this->getFileSize($disk, $csvPath) + $this->getFileSize($disk, $pdfPath);
    }

    private function getFileSize(string $disk, ?string $path): int
    {
        if (blank($path) || ! Storage::disk($disk)->exists($path)) {
            return 0;
        }

        return Storage::disk($disk)->size($path);
    }

    private function ensureDirectoryExists(): void
    {
        $dir = $this->fullPath($this->directory());

        when(! is_dir($dir), fn (): bool => mkdir($dir, 0755, recursive: true));
    }

    private function fullPath(string $relativePath): string
    {
        return Storage::disk($this->disk())->path($relativePath);
    }

    private function directory(): string
    {
        return (string) config('reporting.directory', 'reports');
    }

    private function disk(): string
    {
        return (string) config('reporting.disk', 'local');
    }
}
