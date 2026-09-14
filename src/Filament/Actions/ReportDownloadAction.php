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
use Reporting\Models\Report;
use Reporting\Services\GeneratorDiscovery;
use Reporting\Services\ReportExecutor;
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
            Notification::make()
                ->title('Report Not Found')
                ->body(sprintf('No report configured for generator "%s".', class_basename($generatorClass)))
                ->danger()
                ->send();

            return null;
        }

        $format = $data['_format'] ?? ($report->generate_pdf ? 'pdf' : 'csv');
        unset($data['_format']);

        try {
            $history = app(ReportExecutor::class)->execute($report, $data, auth()->id());
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Report Generation Failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return null;
        }

        /** @var string $disk */
        $disk = config('reporting.disk', 'local');

        if ($format === 'pdf' && filled($history->pdf_file_path)) {
            return response()->streamDownload(
                function () use ($disk, $history): void {
                    echo Storage::disk($disk)->get($history->pdf_file_path);
                },
                basename((string) $history->pdf_file_path),
                ['Content-Type' => 'application/pdf'],
            );
        }

        if (filled($history->csv_file_path)) {
            return response()->streamDownload(
                function () use ($disk, $history): void {
                    echo Storage::disk($disk)->get($history->csv_file_path);
                },
                basename((string) $history->csv_file_path),
                ['Content-Type' => 'text/csv'],
            );
        }

        Notification::make()
            ->title('No File Available')
            ->body('The report completed but no downloadable file was produced.')
            ->warning()
            ->send();

        return null;
    }

    /**
     * Build modifier form fields from the report's generator definition,
     * skipping any modifier whose name is already present in $presetModifiers.
     *
     * @param  array<string, mixed>  $presetModifiers
     *
     * @return array<int, DatePicker|Select|TextInput>
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
            ->map(fn (array $modifier): DatePicker|Select|TextInput|null => self::buildField($modifier))
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Map a single modifier definition to a Filament form field.
     *
     * @param  array<string, mixed>  $modifier
     */
    private static function buildField(array $modifier): DatePicker|Select|TextInput|null
    {
        $type = $modifier['type'] ?? 'text';

        return match ($type) {
            'date_range', 'date' => DatePicker::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'select' => Select::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->options($modifier['options'] ?? [])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'multiselect' => Select::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->options($modifier['options'] ?? [])
                ->multiple()
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            'number' => TextInput::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->numeric()
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),

            default => TextInput::make($modifier['name'])
                ->label($modifier['label'] ?? $modifier['name'])
                ->required($modifier['required'] ?? false)
                ->default($modifier['default'] ?? null),
        };
    }
}
