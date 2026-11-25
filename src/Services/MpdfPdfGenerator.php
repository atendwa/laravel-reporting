<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Contracts\PdfGeneratorContract;
use Reporting\Support\RowValue;
use Reporting\Utilities\CrossTabTransformer;
use Reporting\Utilities\ModifierDisplayValues;
use Reporting\Utilities\NumericFormatter;

/**
 * mPDF incremental WriteHTML approach.
 *
 * Writes HTML to a single mPDF instance in chunks, avoiding loading
 * the entire dataset into one HTML document. No temp files or merge
 * step needed — single pass, bounded memory per chunk.
 */
final class MpdfPdfGenerator implements PdfGeneratorContract
{
    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<string, mixed>  $modifiers
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null  $groupingConfig
     *
     * @throws MpdfException
     */
    public function generate(
        GeneratorInterface $generator,
        Collection $data,
        array $modifiers,
        string $fullPath,
        string $orientation = 'landscape',
        ?array $groupingConfig = null,
    ): void {
        $font = $this->resolveFont();
        $mpdf = $this->newDocument($orientation, $font['mpdf_config']);

        $mpdf->WriteHTML($this->buildCss($font['family']), HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($this->buildHeaderHtml($generator, $modifiers));

        if ($groupingConfig !== null && $data->isNotEmpty()) {
            $this->writeGroupedTable($mpdf, $data, $groupingConfig);
        } else {
            $this->writeFlatTable($mpdf, $generator, $data);
        }

        $mpdf->WriteHTML($this->buildFooterHtml());
        $mpdf->Output($fullPath, Destination::FILE);
    }

    /**
     * @param  array<string, mixed>  $fontConfig
     *
     * @throws MpdfException
     */
    private function newDocument(string $orientation, array $fontConfig): Mpdf
    {
        return new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => $orientation === 'landscape' ? 'A4-L' : 'A4',
            'tempDir' => storage_path('framework/mpdf'),
            'margin_left' => 24,
            'margin_right' => 24,
            'margin_top' => 24,
            'margin_bottom' => 24,
            'default_font_size' => 11,
        ], $fontConfig));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}  $groupingConfig
     */
    private function writeGroupedTable(Mpdf $mpdf, Collection $data, array $groupingConfig): void
    {
        $pivoted = (new CrossTabTransformer)->pivot(
            $data,
            $groupingConfig['group_rows_by'],
            $groupingConfig['group_columns_by'],
            $groupingConfig['group_value_column'],
        );

        $formatter = new NumericFormatter;
        $static = $pivoted['static_columns'];
        $groups = $pivoted['column_groups'];

        $rows = collect($pivoted['rows'])->map(fn (array $row, string|int $rowKey): string => $this->groupedRowHtml((string) $rowKey, $row, $static, $groups, $formatter))->implode('');

        $mpdf->WriteHTML('<table><thead><tr>' . $this->groupedHeadHtml(Str::headline($groupingConfig['group_rows_by']), $static, $groups) . '</tr></thead><tbody>'
            . $rows . $this->groupedTotalsHtml($pivoted['aggregates'], $static, $groups, $formatter) . '</tbody></table>');
    }

    /**
     * @param  array<int, string>  $static
     * @param  array<int, string|int>  $groups
     */
    private function groupedHeadHtml(string $rowLabel, array $static, array $groups): string
    {
        return '<th>' . e($rowLabel) . '</th>'
            . collect($static)->map(fn (string $column): string => '<th>' . e(Str::headline($column)) . '</th>')->implode('')
            . collect($groups)->map(fn (string|int $group): string => '<th class="numeric">' . e((string) $group) . '</th>')->implode('');
    }

    /**
     * @param  array<string|int, mixed>  $row
     * @param  array<int, string>  $static
     * @param  array<int, string|int>  $groups
     */
    private function groupedRowHtml(string $rowKey, array $row, array $static, array $groups, NumericFormatter $formatter): string
    {
        return '<tr><td>' . e($rowKey) . '</td>'
            . collect($static)->map(fn (string $column): string => '<td>' . e((string) ($row[$column] ?? '')) . '</td>')->implode('')
            . collect($groups)->map(fn (string|int $group): string => '<td class="numeric">' . e($formatter->format($row[$group] ?? 0)) . '</td>')->implode('')
            . '</tr>';
    }

    /**
     * @param  array<string|int, mixed>  $aggregates
     * @param  array<int, string>  $static
     * @param  array<int, string|int>  $groups
     */
    private function groupedTotalsHtml(array $aggregates, array $static, array $groups, NumericFormatter $formatter): string
    {
        return '<tr class="total-row"><td>Totals</td>' . str_repeat('<td></td>', count($static))
            . collect($groups)->map(fn (string|int $group): string => '<td class="numeric">' . e($formatter->format($aggregates[$group])) . '</td>')->implode('')
            . '</tr>';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     */
    private function writeFlatTable(Mpdf $mpdf, GeneratorInterface $generator, Collection $data): void
    {
        $headers = $generator->getHeaders();
        $chunkSize = (int) config('reporting.pdf_chunk_size', 500);
        $numericFormatter = new NumericFormatter;
        $numericColumns = $this->detectNumericColumns($data, $headers);

        $mpdf->WriteHTML($this->buildTableOpenHtml($headers, $numericColumns));

        $data->chunk($chunkSize)->each(function (Collection $chunk) use (
            $mpdf,
            $headers,
            $numericFormatter,
            $numericColumns
        ): void {
            $mpdf->WriteHTML($this->buildRowsHtml($chunk, $headers, $numericFormatter, $numericColumns));
        });

        $mpdf->WriteHTML('</tbody></table>');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<int, string>  $headers
     *
     * @return array<string, bool>
     */
    private function detectNumericColumns(Collection $data, array $headers): array
    {
        if ($data->isEmpty()) {
            return [];
        }

        $formatter = new NumericFormatter;

        return collect($headers)->mapWithKeys(fn (string $header): array => [$header => $data->every(function (array $row) use ($header, $formatter): bool {
            $value = RowValue::for($row, $header);

            return blank($value) || $formatter->isNumeric($value);
        })])->all();
    }

    /**
     * @return array{family: string, mpdf_config: array<string, mixed>}
     */
    private function resolveFont(): array
    {
        $name = config('reporting.pdf_font.name');
        $path = config('reporting.pdf_font.path');

        if (blank($name) || blank($path) || ! is_file($path)) {
            return ['family' => 'Arial, Helvetica, sans-serif', 'mpdf_config' => []];
        }

        return [
            'family' => $name,
            'mpdf_config' => [
                'default_font' => $name,
                'fontDir' => [dirname($path)],
                'fontdata' => [
                    $name => ['R' => basename($path)],
                ],
            ],
        ];
    }

    private function buildCss(string $fontFamily): string
    {
        return str_replace('%font%', $fontFamily, (string) file_get_contents(__DIR__ . '/../../resources/css/mpdf.css'));
    }

    /**
     * @param  array<string, mixed>  $modifiers
     */
    private function buildHeaderHtml(GeneratorInterface $generator, array $modifiers): string
    {
        $description = e($generator->getDescription());

        return '<table class="header-row" style="margin-bottom:14px"><tr>'
            . '<td>' . $this->buildLogoHtml() . '</td>'
            . '<td style="text-align:right">'
            . sprintf('<div class="doc-title">%s</div>', e($generator->getName()))
            . ($description !== '' ? sprintf('<div class="doc-ref">%s</div>', $description) : '')
            . sprintf('<div class="doc-generated">Generated: %s</div>', e(now()->format('d M Y, H:i')))
            . '</td></tr></table>'
            . $this->filtersHtml($generator, $modifiers);
    }

    /**
     * @param  array<string, mixed>  $modifiers
     */
    private function filtersHtml(GeneratorInterface $generator, array $modifiers): string
    {
        if ($modifiers === []) {
            return '';
        }

        $parts = collect((new ModifierDisplayValues)->resolve($generator, $modifiers))
            ->map(fn (string $value, string $label): string => e($label) . ': ' . e($value));

        return '<div class="filters"><span class="filters-label">Filters:</span> ' . $parts->implode(' | ') . '</div>';
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, bool>  $numericColumns
     */
    private function buildTableOpenHtml(array $headers, array $numericColumns = []): string
    {
        $html = '<table><thead><tr>';

        foreach ($headers as $header) {
            $class = $numericColumns[$header] ?? false ? ' class="numeric"' : '';
            $html .= '<th' . $class . '>' . e($header) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        return $html;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $headers
     * @param  array<string, bool>  $numericColumns
     */
    private function buildRowsHtml(
        Collection $rows,
        array $headers,
        NumericFormatter $numericFormatter,
        array $numericColumns = []
    ): string {
        return $rows->map(fn (array $row): string => '<tr>' . collect($headers)->map(function (string $header) use ($row, $numericFormatter, $numericColumns): string {
            $value = RowValue::for($row, $header);
            $isNumeric = $numericColumns[$header] ?? false;

            return '<td' . ($isNumeric ? ' class="numeric"' : '') . '>' . e($isNumeric ? $numericFormatter->format($value) : (string) $value) . '</td>';
        })->implode('') . '</tr>')->implode('');
    }

    private function buildLogoHtml(): string
    {
        $logoPath = config('reporting.logo_path');

        if (blank($logoPath) || ! file_exists($logoPath)) {
            return '';
        }

        $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
        $src = 'data:image/' . $ext . ';base64,' . base64_encode((string) file_get_contents($logoPath));

        return '<img src="' . $src . '" class="logo" />';
    }

    private function buildFooterHtml(): string
    {
        return '';
    }
}
