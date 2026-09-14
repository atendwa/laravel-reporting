<?php

declare(strict_types=1);

use Reporting\Models\Report;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

it('creates a schedule via factory', function (): void {
    $schedule = ReportingSchedule::factory()->create();

    expect($schedule)
        ->toBeInstanceOf(ReportingSchedule::class)
        ->name->not->toBeNull()
        ->cron_expression->not->toBeNull()
        ->is_active->toBeTrue();
});

it('creates daily schedule', function (): void {
    $schedule = ReportingSchedule::factory()->daily()->create();

    expect($schedule->cron_expression)->toBe('0 8 * * *');
});

it('creates weekly schedule', function (): void {
    $schedule = ReportingSchedule::factory()->weekly()->create();

    expect($schedule->cron_expression)->toBe('0 8 * * 1');
});

it('creates monthly schedule', function (): void {
    $schedule = ReportingSchedule::factory()->monthly()->create();

    expect($schedule->cron_expression)->toBe('0 8 1 * *');
});

it('has reports relationship', function (): void {
    $schedule = ReportingSchedule::factory()->create();
    $report = Report::factory()->create();

    ReportSchedule::factory()->create([
        'report_id' => $report->id,
        'reporting_schedule_id' => $schedule->id,
    ]);

    expect($schedule->reports)->toHaveCount(1);
});

it('supports multiple reports per schedule', function (): void {
    $schedule = ReportingSchedule::factory()->create();
    $reports = Report::factory()->count(3)->create();

    $reports->each(fn (Report $report) => ReportSchedule::factory()->create([
        'report_id' => $report->id,
        'reporting_schedule_id' => $schedule->id,
    ]));

    expect($schedule->refresh()->reports)->toHaveCount(3);
})->todo();
