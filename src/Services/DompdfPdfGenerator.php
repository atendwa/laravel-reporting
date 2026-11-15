<?php

declare(strict_types=1);

namespace Reporting\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Contracts\PdfGeneratorContract;
use Reporting\Utilities\CrossTabTransformer;
use Reporting\Utilities\ModifierDisplayValues;
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
     *
     * @throws MpdfException
     * @throws PdfParserException
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
    private function generateGroupedPdf(GeneratorInterface $generator, Collection $data, array $modifiers, string $fullPath, string $orientation, array $groupingConfig): void
    {
        $pivoted = (new CrossTabTransformer)->pivot($data, $groupingConfig['group_rows_by'], $groupingConfig['group_columns_by'], $groupingConfig['group_value_column']);

        Pdf::loadView('reporting::pdf.grouped', [
            ...$this->documentData($generator, $modifiers),
            'rowLabel' => Str::headline($groupingConfig['group_rows_by']),
            'staticColumns' => $pivoted['static_columns'],
            'columnGroups' => $pivoted['column_groups'],
            'aggregates' => $pivoted['aggregates'],
            'rows' => $pivoted['rows'],
        ])->setPaper('a4', $orientation)->save($fullPath);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     *
     * @throws MpdfException
     * @throws PdfParserException
     */
    private function generateFlatPdf(GeneratorInterface $generator, Collection $data, array $modifiers, string $fullPath, string $orientation): void
    {
        $chunks = $data->chunk((int) config('reporting.pdf_chunk_size', 500));

        if ($chunks->count() <= 1) {
            $this->renderChunk($generator, $chunks->first() ?? collect(), $modifiers, $fullPath, $orientation, showHeader: true, showFooter: true);

            return;
        }

        $tempFiles = $chunks->values()->map(fn (): string => storage_path('framework/temp/rpt_chunk_' . Str::random(12) . '.pdf'))->all();

        try {
            $this->renderChunks($generator, $chunks, $tempFiles, $modifiers, $orientation);
            $this->mergeChunks($tempFiles, $fullPath, $orientation);
        } finally {
            collect($tempFiles)->filter(fn (string $file): bool => file_exists($file))->each(fn (string $file): bool => unlink($file));
        }
    }

    /**
     * @param  Collection<int, Collection<int, array<string, mixed>>>  $chunks
     * @param  array<int, string>  $tempFiles
     * @param  array<string, mixed>  $modifiers
     */
    private function renderChunks(GeneratorInterface $generator, Collection $chunks, array $tempFiles, array $modifiers, string $orientation): void
    {
        File::ensureDirectoryExists(storage_path('framework/temp'));
        $lastIndex = $chunks->count() - 1;

        $chunks->values()->each(fn (Collection $chunk, int $index) => $this->renderChunk(
            $generator, $chunk, $modifiers, $tempFiles[$index], $orientation, showHeader: $index === 0, showFooter: $index === $lastIndex,
        ));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $modifiers
     */
    private function renderChunk(GeneratorInterface $generator, Collection $rows, array $modifiers, string $outputPath, string $orientation, bool $showHeader, bool $showFooter): void
    {
        Pdf::loadView('reporting::pdf.chunk', [
            ...$this->documentData($generator, $modifiers),
            'headers' => $generator->getHeaders(),
            'showHeader' => $showHeader,
            'showFooter' => $showFooter,
            'rows' => $rows,
        ])->setPaper('a4', $orientation)->save($outputPath);
    }

    /**
     * @param  array<string, mixed>  $modifiers
     *
     * @return array{modifiers: array<string, string>, description: string, generatedAt: string, reportName: string}
     */
    private function documentData(GeneratorInterface $generator, array $modifiers): array
    {
        return [
            'modifiers' => (new ModifierDisplayValues)->resolve($generator, $modifiers),
            'description' => $generator->getDescription(),
            'generatedAt' => now()->format('d M Y, H:i'),
            'reportName' => $generator->getName(),
        ];
    }

    /**
     * @param  array<int, string>  $tempFiles
     *
     * @throws MpdfException
     * @throws PdfParserException
     */
    private function mergeChunks(array $tempFiles, string $outputPath, string $orientation): void
    {
        $isLandscape = $orientation === 'landscape';
        $mpdf = new Mpdf([
            'tempDir' => storage_path('framework/mpdf'),
            'format' => $isLandscape ? [297, 210] : [210, 297],
            'margin_bottom' => 0,
            'margin_right' => 0,
            'margin_left' => 0,
            'margin_top' => 0,
        ]);

        foreach ($tempFiles as $fileIndex => $tempFile) {
            $this->appendPages($mpdf, $tempFile, $isLandscape ? 'L' : 'P', isFirstFile: $fileIndex === 0);
        }

        $mpdf->Output($outputPath, Destination::FILE);
    }

    /**
     * @throws MpdfException
     * @throws PdfParserException
     */
    private function appendPages(Mpdf $mpdf, string $file, string $orientation, bool $isFirstFile): void
    {
        $pageCount = $mpdf->setSourceFile($file);

        for ($page = 1; $page <= $pageCount; ++$page) {
            if (! $isFirstFile || $page > 1) {
                $mpdf->AddPage($orientation);
            }

            $mpdf->useTemplate($mpdf->importPage($page));
        }
    }
}
