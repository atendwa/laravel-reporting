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
use Reporting\Utilities\CrossTabTransformer;
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
        $format = $orientation === 'landscape' ? 'A4-L' : 'A4';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'tempDir' => storage_path('framework/mpdf'),
            'margin_left' => 24,
            'margin_right' => 24,
            'margin_top' => 24,
            'margin_bottom' => 24,
            'default_font' => 'futuralt',
            'default_font_size' => 11,
            'fontDir' => [public_path('fonts')],
            'fontdata' => [
                'futuralt' => ['R' => 'futuralt.ttf'],
            ],
        ]);

        $mpdf->WriteHTML($this->buildCss(), HTMLParserMode::HEADER_CSS);
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
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array{group_rows_by: string, group_columns_by: string, group_value_column: string}  $groupingConfig
     */
    private function writeGroupedTable(Mpdf $mpdf, Collection $data, array $groupingConfig): void
    {
        $crossTabTransformer = new CrossTabTransformer;
        $numericFormatter = new NumericFormatter;

        $pivoted = $crossTabTransformer->pivot(
            $data,
            $groupingConfig['group_rows_by'],
            $groupingConfig['group_columns_by'],
            $groupingConfig['group_value_column'],
        );

        $staticColumns = $pivoted['static_columns'];
        $columnGroups = $pivoted['column_groups'];
        $rowLabel = Str::headline($groupingConfig['group_rows_by']);

        $html = '<table><thead><tr>';
        $html .= '<th>' . e($rowLabel) . '</th>';

        foreach ($staticColumns as $col) {
            $html .= '<th>' . e(Str::headline($col)) . '</th>';
        }

        foreach ($columnGroups as $colGroup) {
            $html .= '<th class="numeric">' . e((string) $colGroup) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($pivoted['rows'] as $rowKey => $row) {
            $html .= '<tr>';
            $html .= '<td>' . e((string) $rowKey) . '</td>';

            foreach ($staticColumns as $staticColumn) {
                $html .= '<td>' . e((string) ($row[$staticColumn] ?? '')) . '</td>';
            }

            foreach ($columnGroups as $columnGroup) {
                $html .= '<td class="numeric">' . e($numericFormatter->format($row[$columnGroup] ?? 0)) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '<tr class="total-row">';
        $html .= '<td>Totals</td>';

        foreach ($staticColumns as $staticColumn) {
            $html .= '<td></td>';
        }

        foreach ($columnGroups as $colGroup) {
            $html .= '<td class="numeric">' . e($numericFormatter->format($pivoted['aggregates'][$colGroup])) . '</td>';
        }

        $html .= '</tr>';
        $html .= '</tbody></table>';

        $mpdf->WriteHTML($html);
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
            $mpdf, $headers, $numericFormatter, $numericColumns
        ): void {
            $mpdf->WriteHTML($this->buildRowsHtml($chunk, $headers, $numericFormatter, $numericColumns));
        });

        $mpdf->WriteHTML('</tbody></table>');
    }

    /**
     * Scan all data to determine which columns contain exclusively numeric values.
     *
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

        $numericFormatter = new NumericFormatter;
        $numericColumns = [];

        foreach ($headers as $header) {
            $key = Str::snake($header);
            $allNumeric = true;

            foreach ($data as $row) {
                $value = $row[$key] ?? $row[$header] ?? '';

                if (blank($value)) {
                    continue;
                }

                if (! $numericFormatter->isNumeric($value)) {
                    $allNumeric = false;

                    break;
                }
            }

            $numericColumns[$header] = $allNumeric;
        }

        return $numericColumns;
    }

    private function buildCss(): string
    {
        return <<<'CSS'
        body {
            font-family: futuralt, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .header-row td {
            border-bottom: none;
            padding-bottom: 0;
        }
        .doc-title {
            font-size: 16px;
            color: #02338d;
            text-align: right;
            margin-bottom: 8px;
        }
        .doc-ref {
            font-size: 11px;
            color: #374151;
            text-align: right;
            margin-bottom: 5px;
        }
        .doc-generated {
            font-size: 11px;
            color: #6b7280;
            text-align: right;
            margin-top: 8px;
        }
        .logo {
            height: 85px;
            margin-left: -20px;
        }
        .filters {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 10px;
            color: #374151;
            margin-bottom: 14px;
        }
        .filters-label {
            color: #02338d;
        }
        thead th {
            background-color: #02338d;
            color: #ffffff;
            padding: 5px 8px;
            text-align: left;
            font-weight: 600;
        }
        thead th.numeric {
            text-align: right;
        }
        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
            vertical-align: middle;
        }
        tbody td.numeric {
            text-align: right;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .total-row td {
            border-top: 2px solid #02338d;
            padding: 5px 8px;
            font-size: 11px;
            color: #1f2937;
        }
        .total-row td.numeric {
            text-align: right;
            color: #02338d;
        }
        CSS;
    }

    /**
     * @param  array<string, mixed>  $modifiers
     */
    private function buildHeaderHtml(GeneratorInterface $generator, array $modifiers): string
    {
        $name = e($generator->getName());
        $description = e($generator->getDescription());
        $generatedAt = e(now()->format('d M Y, H:i'));

        $html = '<table class="header-row" style="margin-bottom:14px">';
        $html .= '<tr>';
        $html .= '<td>' . $this->buildLogoHtml() . '</td>';
        $html .= '<td style="text-align:right">';
        $html .= sprintf('<div class="doc-title">%s</div>', $name);

        if ($description !== '') {
            $html .= sprintf('<div class="doc-ref">%s</div>', $description);
        }

        $html .= sprintf('<div class="doc-generated">Generated: %s</div>', $generatedAt);
        $html .= '</td></tr></table>';

        if ($modifiers !== []) {
            $resolved = $this->resolveModifierDisplayValues($generator, $modifiers);
            $html .= '<div class="filters"><span class="filters-label">Filters:</span> ';
            $parts = [];

            foreach ($resolved as $label => $displayValue) {
                $parts[] = e($label) . ': ' . e($displayValue);
            }

            $html .= implode(' | ', $parts);
            $html .= '</div>';
        }

        return $html;
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
        $html = '';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($headers as $header) {
                $value = $row[Str::snake($header)] ?? $row[$header] ?? '';
                $isNumericColumn = $numericColumns[$header] ?? false;
                $class = $isNumericColumn ? ' class="numeric"' : '';
                $display = $isNumericColumn ? $numericFormatter->format($value) : (string) $value;
                $html .= '<td' . $class . '>' . e($display) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html;
    }

    private function buildLogoHtml(): string
    {
        $logoPath = public_path('images/branding/logo.png');

        if (! file_exists($logoPath)) {
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
