<?php

declare(strict_types=1);

use Reporting\Consts\ReportStatus;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

it('creates history via factory', function (): void {
    $history = ReportHistory::factory()->create();

    expect($history)
        ->toBeInstanceOf(ReportHistory::class)
        ->status->toBe(ReportStatus::STATUS_PENDING);
});

it('creates completed history', function (): void {
    $history = ReportHistory::factory()->completed()->create();

    expect($history->isCompleted())->toBeTrue();
    expect($history->csv_file_path)->not->toBeNull();
    expect($history->pdf_file_path)->not->toBeNull();
});

it('creates failed history', function (): void {
    $history = ReportHistory::factory()->failed()->create();

    expect($history->isFailed())->toBeTrue();
    expect($history->error_message)->not->toBeNull();
});

it('creates processing history', function (): void {
    $history = ReportHistory::factory()->processing()->create();

    expect($history->isProcessing())->toBeTrue();
});

it('belongs to a report', function (): void {
    $report = Report::factory()->create();
    $history = ReportHistory::factory()->for($report)->create();

    expect($history->report->id)->toBe($report->id);
});

it('formats execution time in milliseconds', function (): void {
    $history = ReportHistory::factory()->create([
        'execution_time_ms' => 500,
    ]);

    expect($history->formattedExecutionTime())->toBe('500ms');
});

it('formats execution time in seconds', function (): void {
    $history = ReportHistory::factory()->create([
        'execution_time_ms' => 2500,
    ]);

    expect($history->formattedExecutionTime())->toBe('2.5s');
});

it('formats file size', function (): void {
    $history = ReportHistory::factory()->create([
        'file_size' => 1536,
    ]);

    expect($history->formattedFileSize())->toBe('1.5 KB');
});

it('returns dash for null execution time', function (): void {
    $history = ReportHistory::factory()->create([
        'execution_time_ms' => null,
    ]);

    expect($history->formattedExecutionTime())->toBe('-');
});

it('casts modifiers as array', function (): void {
    $history = ReportHistory::factory()->create([
        'modifiers' => ['category' => 'Alpha'],
    ]);

    expect($history->fresh()->modifiers)
        ->toBeArray()
        ->toMatchArray(['category' => 'Alpha']);
});
