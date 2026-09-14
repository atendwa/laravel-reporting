<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Reporting\Generator\TestGenerator;
use Reporting\Jobs\GenerateReportJob;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Reporting\Models\ReportSchedule;

beforeEach(function (): void {
    ReportHistory::query()->delete();
    ReportSchedule::query()->delete();
    Report::query()->forceDelete();
});

it('dispatches to the reports queue', function (): void {
    Queue::fake();

    $report = Report::query()->firstWhere('generator_class', TestGenerator::class);

    if (blank($report)) {
        $report = Report::factory()->create([
            'generator_class' => TestGenerator::class,
        ]);
    }

    GenerateReportJob::dispatch($report, ['category' => 'Alpha']);

    Queue::assertPushed(GenerateReportJob::class, fn ($job): bool => $job->report->id === $report->id
        && $job->modifiers === ['category' => 'Alpha']);
});
//
// it('generates report when handled', function (): void {
//    Event::fake();
//
//    $report = Report::factory()->create([
//        'generator_class' => TestGenerator::class,
//        'is_heavy' => false,
//    ]);
//
//    $job = new GenerateReportJob($report, [], 1);
//    $job->handle(app(Reporting\Services\ReportExecutor::class));
//
//    $history = ReportHistory::query()
//        ->where('report_id', $report->id)
//        ->latest()
//        ->first();
//
//    expect($history->status)->toBe(ReportStatus::STATUS_COMPLETED);
//
//    Event::assertDispatched(ReportGenerated::class);
// });

it('constructs with correct properties', function (): void {
    $report = Report::factory()->create([
        'generator_class' => TestGenerator::class,
    ]);

    $job = new GenerateReportJob($report, ['category' => 'Alpha'], 5);

    expect($job->report->id)->toBe($report->id);
    expect($job->modifiers)->toBe(['category' => 'Alpha']);
    expect($job->userId)->toBe(5);
});
