<?php

declare(strict_types=1);

use Reporting\Models\Report;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

it('creates a report via factory', function (): void {
    $report = Report::factory()->create();

    expect($report)
        ->toBeInstanceOf(Report::class)
        ->name->not->toBeNull()
        ->generator_class->not->toBeNull()
        ->is_active->toBeTrue();
});

it('creates a heavy report', function (): void {
    $report = Report::factory()->heavy()->create();

    expect($report->is_heavy)->toBeTrue();
});

it('creates an inactive report', function (): void {
    $report = Report::factory()->inactive()->create();

    expect($report->is_active)->toBeFalse();
});

// it('has histories relationship', function (): void {
//    $report = Report::factory()->create();
//    ReportHistory::factory()->count(3)->for($report)->create();
//
//    expect($report->histories()->get())->toHaveCount(3);
// });

it('has schedules relationship via pivot', function (): void {
    $report = Report::factory()->create();
    $schedule = ReportingSchedule::factory()->create();

    ReportSchedule::factory()->create([
        'report_id' => $report->id,
        'reporting_schedule_id' => $schedule->id,
    ]);

    expect($report->schedules)->toHaveCount(1);
    expect($report->reportSchedules)->toHaveCount(1);
});
