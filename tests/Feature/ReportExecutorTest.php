<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Reporting\Consts\ReportSource;
use Reporting\Consts\ReportStatus;
use Reporting\Events\ReportFailed;
use Reporting\Events\ReportGenerated;
use Reporting\Events\ReportGenerationStarted;
use Reporting\Generator\TestGenerator;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Reporting\Models\ReportSchedule;
use Reporting\Services\ReportExecutor;

beforeEach(function (): void {
    Event::fake();

    ReportHistory::query()->delete();
    ReportSchedule::query()->delete();
    Report::query()->forceDelete();
});

it('executes a report and creates history', function (): void {
    $report = Report::factory()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report, [], 1);

    expect($reportHistory->status)->toBe(ReportStatus::STATUS_COMPLETED);
    expect($reportHistory->row_count)->toBe(3);
    expect($reportHistory->csv_file_path)->not->toBeNull();
    expect($reportHistory->execution_time_ms)->not->toBeNull();
    expect($reportHistory->triggered_by_user_id)->toBe(1);
    expect($reportHistory->source)->toBe(ReportSource::SOURCE_MANUAL);

    Event::assertDispatched(ReportGenerationStarted::class);
    Event::assertDispatched(ReportGenerated::class);
});

it('captures modifiers in history', function (): void {
    $report = Report::factory()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    $modifiers = ['category' => 'Alpha'];
    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report, $modifiers);

    expect($reportHistory->modifiers)->toMatchArray($modifiers);
    expect($reportHistory->row_count)->toBe(2);
});

it('executes heavy reports without blocking', function (): void {
    $report = Report::factory()->heavy()->create([
        'generator_class' => TestGenerator::class,
    ]);

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report);

    expect($reportHistory->status)->toBe(ReportStatus::STATUS_COMPLETED);
});

it('fires failure event on exception', function (): void {
    $report = Report::factory()->create([
        'generator_class' => 'NonExistent\\Generator',
        'is_heavy' => false,
    ]);

    $reportExecutor = app(ReportExecutor::class);

    try {
        $reportExecutor->execute($report);
    } catch (Throwable) {
        // Expected
    }

    Event::assertDispatched(ReportFailed::class);
});

it('sets scheduled source correctly', function (): void {
    $report = Report::factory()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute(
        $report,
        [],
        source: ReportSource::SOURCE_SCHEDULED,
    );

    expect($reportHistory->source)->toBe(ReportSource::SOURCE_SCHEDULED);
    expect($reportHistory->triggered_by_user_id)->toBeNull();
});

it('generates only csv when generate_pdf is false', function (): void {
    $report = Report::factory()->csvOnly()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report);

    expect($reportHistory->status)->toBe(ReportStatus::STATUS_COMPLETED);
    expect($reportHistory->csv_file_path)->not->toBeNull();
    expect($reportHistory->pdf_file_path)->toBeNull();
});

it('generates only pdf when generate_csv is false', function (): void {
    $report = Report::factory()->pdfOnly()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report);

    expect($reportHistory->status)->toBe(ReportStatus::STATUS_COMPLETED);
    expect($reportHistory->csv_file_path)->toBeNull();
    expect($reportHistory->pdf_file_path)->not->toBeNull();
});

it('generates pdf in portrait when report orientation is portrait', function (): void {
    $report = Report::factory()->portrait()->pdfOnly()->create([
        'generator_class' => TestGenerator::class,
        'is_heavy' => false,
    ]);

    expect($report->orientation)->toBe('portrait');

    $reportExecutor = app(ReportExecutor::class);
    $reportHistory = $reportExecutor->execute($report);

    expect($reportHistory->status)->toBe(ReportStatus::STATUS_COMPLETED);
    expect($reportHistory->pdf_file_path)->not->toBeNull();
});
