<?php

declare(strict_types=1);

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Reporting\Filament\Actions\ReportDownloadAction;
use Reporting\Generator\TestGenerator;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    Event::fake();
    Storage::fake('local');
    config(['reporting.disk' => 'local']);
    Report::query()->forceDelete();
});

function downloadReport(string $generator, array $data = []): mixed
{
    return Closure::bind(fn (): mixed => self::handleDownload($generator, $data), null, ReportDownloadAction::class)();
}

function streamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('says so when no report uses the generator', function (): void {
    expect(downloadReport(TestGenerator::class))->toBeNull();

    Notification::assertNotified('Report Not Found');
});

it('streams the CSV of a report that has no PDF', function (): void {
    Report::factory()->create(['generator_class' => TestGenerator::class, 'is_heavy' => false, 'generate_pdf' => false]);

    $response = downloadReport(TestGenerator::class);
    $history = ReportHistory::query()->sole();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv')
        ->and(streamed($response))->toBe(Storage::disk('local')->get($history->csv_file_path));
});

it('streams the PDF when it is asked for and was produced', function (): void {
    Report::factory()->create(['generator_class' => TestGenerator::class, 'is_heavy' => false, 'generate_pdf' => true]);

    $response = downloadReport(TestGenerator::class, ['_format' => 'pdf']);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(streamed($response))->toStartWith('%PDF');
});

it('tells the user when the report fails', function (): void {
    Report::factory()->create(['generator_class' => 'App\\Reports\\Generators\\Missing', 'is_heavy' => false]);

    expect(downloadReport('App\\Reports\\Generators\\Missing'))->toBeNull();

    Notification::assertNotified('Report Generation Failed');
});

it('tells the user when the run produced no file', function (): void {
    Report::factory()->create(['generator_class' => TestGenerator::class, 'is_heavy' => false, 'generate_csv' => false, 'generate_pdf' => false]);

    expect(downloadReport(TestGenerator::class))->toBeNull();

    Notification::assertNotified('No File Available');
});
