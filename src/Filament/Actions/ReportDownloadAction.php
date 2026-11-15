<?php

declare(strict_types=1);

namespace Reporting\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Reporting\Exceptions\GeneratorNotFoundException;
use Reporting\Filament\Support\ModifierField;
use Reporting\Models\Report;
use Reporting\Services\GeneratorDiscovery;
use Reporting\Services\ReportExecutor;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Factory that produces a Filament Action for synchronous (in-browser) report download.
 *
 * Usage:
 *   ReportDownloadAction::for(MonthlySalesGenerator::class)
 *       ->label('Download Sales Report')
 *       ->color('primary')
 *
 * The returned Action resolves the report by generator_class, builds a modifier form (if any),
 * optionally presents a format selector (PDF / CSV), runs the generator synchronously,
 * and streams the resulting file directly to the browser — no queue, no job.
 */
final class ReportDownloadAction
{
    /**
     * Create a pre-configured Filament Action that downloads the report in-browser.
     *
     * Pre-set modifiers are injected directly and their form fields are hidden from the modal.
     * If all required modifiers are pre-set and no format choice is needed, the modal is skipped
     * and the report runs immediately on click.
     *
     * Usage with pre-set modifiers (e.g. on a department dashboard):
     *   ReportDownloadAction::for(DepartmentStaffTopUpGenerator::class, [
     *       'department' => auth()->user()->department_short_name,
     *   ])
     *
     * @param  class-string  $generatorClass  The fully-qualified generator class name.
     * @param  array<string, mixed>  $presetModifiers  Modifier values to inject without showing form fields.
     */
    public static function for(string $generatorClass, array $presetModifiers = []): Action
    {
        return Action::make('download_' . Str::slug(class_basename($generatorClass)))
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading('Download Report')
            ->modalSubmitActionLabel('Download')
            ->schema(fn (): array => self::buildSchema($generatorClass, $presetModifiers))
            ->action(fn (array $data): mixed => self::handleDownload($generatorClass, $data, $presetModifiers));
    }

    /**
     * Build the modal form schema: modifier fields + optional format selector.
     * Fields whose names appear in $presetModifiers are excluded from the schema.
     *
     * @param  class-string  $generatorClass
     * @param  array<string, mixed>  $presetModifiers
     *
     * @return array<int, DatePicker|Select|TextInput>
     *
     * @throws GeneratorNotFoundException
     */
    private static function buildSchema(string $generatorClass, array $presetModifiers = []): array
    {
        $report = Report::query()->where('generator_class', $generatorClass)->first();

        if (! $report) {
            return [];
        }

        $fields = self::buildModifierFields($report, $presetModifiers);

        if ($report->generate_pdf && $report->generate_csv) {
            $fields[] = Select::make('_format')
                ->label('Format')
                ->options(['pdf' => 'PDF', 'csv' => 'CSV / Excel'])
                ->required()
                ->default('pdf');
        }

        return $fields;
    }

    /**
     * Execute the report synchronously and stream the file download.
     * Pre-set modifiers are merged in, taking priority over form data.
     *
     * @param  class-string  $generatorClass
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presetModifiers
     */
    private static function handleDownload(string $generatorClass, array $data, array $presetModifiers = []): mixed
    {
        $data = array_merge($data, $presetModifiers);
        $report = Report::query()->where('generator_class', $generatorClass)->first();

        if (! $report) {
            return self::warn('Report Not Found', sprintf('No report configured for generator "%s".', class_basename($generatorClass)), danger: true);
        }

        $format = $data['_format'] ?? ($report->generate_pdf ? 'pdf' : 'csv');
        unset($data['_format']);

        try {
            $history = app(ReportExecutor::class)->execute($report, $data, auth()->id());
        } catch (Throwable $throwable) {
            return self::warn('Report Generation Failed', $throwable->getMessage(), danger: true);
        }

        return match (true) {
            $format === 'pdf' && filled($history->pdf_file_path) => self::stream((string) $history->pdf_file_path, 'application/pdf'),
            filled($history->csv_file_path) => self::stream((string) $history->csv_file_path, 'text/csv'),
            default => self::warn('No File Available', 'The report completed but no downloadable file was produced.'),
        };
    }

    private static function stream(string $path, string $contentType): StreamedResponse
    {
        /** @var string $disk */
        $disk = config('reporting.disk', 'local');

        return response()->streamDownload(function () use ($disk, $path): void {
            echo Storage::disk($disk)->get($path);
        }, basename($path), ['Content-Type' => $contentType]);
    }

    private static function warn(string $title, string $body, bool $danger = false): null
    {
        $notification = Notification::make()->title($title)->body($body);
        ($danger ? $notification->danger() : $notification->warning())->send();

        return null;
    }

    /**
     * Build modifier form fields from the report's generator definition,
     * skipping any modifier whose name is already present in $presetModifiers.
     *
     * @param  array<string, mixed>  $presetModifiers
     *
     * @return array<int, DatePicker|Select|TextInput>
     *
     * @throws GeneratorNotFoundException
     */
    private static function buildModifierFields(Report $report, array $presetModifiers = []): array
    {
        $generatorDiscovery = new GeneratorDiscovery;

        if (! $generatorDiscovery->isValidGenerator($report->generator_class)) {
            return [];
        }

        $generator = $generatorDiscovery->resolve($report->generator_class);

        return collect($generator->getModifiers())
            ->reject(fn (array $modifier): bool => array_key_exists($modifier['name'], $presetModifiers))
            ->map(fn (array $modifier): DatePicker|Select|TextInput => ModifierField::make($modifier))
            ->filter()
            ->values()
            ->toArray();
    }
}
