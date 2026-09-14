<?php

declare(strict_types=1);

namespace Reporting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Contracts\PdfGeneratorContract;
use Reporting\Utilities\CrossTabTransformer;
use setasign\Fpdi\PdfParser\PdfParserException;

/**
 * Chunked DomPDF rendering with FPDI merge.
 *
 * Splits data into manageable chunks, renders each as a separate PDF
 * via DomPDF, then merges all pages into a single file using mPDF's
 * built-in FPDI support.
 */
final class DompdfPdfGenerator implements PdfGeneratorContract
{
    /**
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
    ): void {
        if ($groupingConfig !== null && $data->isNotEmpty()) {
            $this->generateGroupedPdf($generator, $data, $modifiers, $fullPath, $orientation, $groupingConfig);

            return;
        }

        $this->generateFlatPdf($generator, $data, $modifiers, $fullPath, $orientation);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}  $groupingConfig
     */
    private function generateGroupedPdf(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers,
        string $fullPath,
        string $orientation,
        array $groupingConfig,
    ): void {
        $crossTabTransformer = new CrossTabTransformer;

        $pivoted = $crossTabTransformer->pivot(
            $data,
            $groupingConfig['group_rows_by'],
            $groupingConfig['group_columns_by'],
            $groupingConfig['group_value_column'],
        );

        $pdf = Pdf::loadView('reporting::pdf.grouped', [
            'modifiers' => $this->resolveModifierDisplayValues($generator, $modifiers),
            'rowLabel' => Str::headline($groupingConfig['group_rows_by']),
            'description' => $generator->getDescription(),
            'staticColumns' => $pivoted['static_columns'],
            'columnGroups' => $pivoted['column_groups'],
            'generatedAt' => now()->format('d M Y, H:i'),
            'aggregates' => $pivoted['aggregates'],
            'reportName' => $generator->getName(),
            'rows' => $pivoted['rows'],
        ])->setPaper('a4', $orientation);

        $pdf->save($fullPath);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     */
    private function generateFlatPdf(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers,
        string $fullPath,
        string $orientation,
    ): void {
        $chunkSize = (int) config('reporting.pdf_chunk_size', 500);
        $chunks = $data->chunk($chunkSize);

        if ($chunks->count() <= 1) {
            $this->renderChunk(
                $generator,
                $chunks->first() ?? collect(),
                $modifiers,
                $fullPath,
                $orientation,
                showHeader: true,
                showFooter: true,
            );

            return;
        }

        $this->generateChunkedPdf($generator, $chunks, $modifiers, $fullPath, $orientation);
    }

    /**
     * @param  Collection<int, Collection<int, array<string, mixed>>>  $chunks
     * @param  array<string, mixed>  $modifiers
     */
    private function generateChunkedPdf(
        GeneratorInterface $generator,
        Collection $chunks,
        array $modifiers,
        string $fullPath,
        string $orientation,
    ): void {
        $tempFiles = [];
        $lastIndex = $chunks->count() - 1;

        try {
            $chunks->each(function (Collection $chunk, int $index) use (
                $generator, $modifiers, &$tempFiles, $lastIndex, $orientation
            ): void {
                $tempPath = storage_path('framework/temp/rpt_chunk_' . Str::random(12) . '.pdf');

                $this->renderChunk(
                    $generator,
                    $chunk,
                    $modifiers,
                    $tempPath,
                    $orientation,
                    showHeader: $index === 0,
                    showFooter: $index === $lastIndex,
                );

                $tempFiles[] = $tempPath;
            });

            $this->mergeChunks($tempFiles, $fullPath, $orientation);
        } finally {
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $modifiers
     */
    private function renderChunk(
        GeneratorInterface $generator,
        Collection $rows,
        array $modifiers,
        string $outputPath,
        string $orientation,
        bool $showHeader,
        bool $showFooter,
    ): void {
        $pdf = Pdf::loadView('reporting::pdf.chunk', [
            'modifiers' => $this->resolveModifierDisplayValues($generator, $modifiers),
            'description' => $generator->getDescription(),
            'generatedAt' => now()->format('d M Y, H:i'),
            'reportName' => $generator->getName(),
            'headers' => $generator->getHeaders(),
            'showHeader' => $showHeader,
            'showFooter' => $showFooter,
            'rows' => $rows,
        ])->setPaper('a4', $orientation);

        $pdf->save($outputPath);
    }

    /**
     * Merge multiple PDF files into one using mPDF's FPDI integration.
     *
     * @param  array<int, string>  $tempFiles
     *
     * @throws MpdfException
     * @throws PdfParserException
     */
    private function mergeChunks(array $tempFiles, string $outputPath, string $orientation): void
    {
        $isLandscape = $orientation === 'landscape';
        $width = $isLandscape ? 297 : 210;
        $height = $isLandscape ? 210 : 297;
        $mpdfOrientation = $isLandscape ? 'L' : 'P';

        $mpdf = new Mpdf([
            'tempDir' => storage_path('framework/mpdf'),
            'format' => [$width, $height],
            'margin_bottom' => 0,
            'margin_right' => 0,
            'margin_left' => 0,
            'margin_top' => 0,
        ]);

        $isFirstPage = true;

        foreach ($tempFiles as $tempFile) {
            $pageCount = $mpdf->setSourceFile($tempFile);

            for ($page = 1; $page <= $pageCount; ++$page) {
                if (! $isFirstPage) {
                    $mpdf->AddPage($mpdfOrientation);
                }

                $template = $mpdf->importPage($page);
                $mpdf->useTemplate($template);
                $isFirstPage = false;
            }
        }

        $mpdf->Output($outputPath, Destination::FILE);
    }

    /**
     * Resolve raw modifier values to human-readable labels using the generator's definitions.
     *
     * @param  array<string, mixed>  $modifiers
     *
     * @return array<string, string>
     */
    private function resolveModifierDisplayValues(GeneratorInterface $generator, array $modifiers): array
    {
        $definitions = collect($generator->getModifiers())->keyBy('name');
        $resolved = [];

        foreach ($modifiers as $key => $value) {
            $definition = $definitions->get($key);
            $label = $definition['label'] ?? ucfirst(str_replace('_', ' ', (string) $key));
            $options = $definition['options'] ?? [];

            if (! empty($options)) {
                if (is_array($value)) {
                    $displayValue = collect($value)
                        ->map(fn (mixed $v): string => (string) ($options[$v] ?? $v))
                        ->implode(', ');
                } else {
                    $displayValue = (string) ($options[$value] ?? $value);
                }
            } else {
                $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
            }

            $resolved[$label] = $displayValue;
        }

        return $resolved;
    }
}
