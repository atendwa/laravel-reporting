<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Reporting\Models\ReportSchedule;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @return list<string>
 */
function scheduledReportCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn (ScheduledEvent $event): string => (string) $event->command)
        ->filter(fn (string $command): bool => str_contains($command, 'reports:run'))
        ->values()
        ->all();
}

function startCommand(string $name): void
{
    event(new CommandStarting($name, new ArrayInput([]), new NullOutput));
}

it('does not query report schedules until a schedule command starts', function (): void {
    ReportSchedule::factory()->create();
    DB::enableQueryLog();

    // The package's console routes resolve the Schedule on every boot; resolve it afresh to replay that.
    app()->forgetInstance(Schedule::class);
    app(Schedule::class);
    startCommand('migrate');

    expect(scheduledReportCommands())->toBeEmpty()
        ->and(collect(DB::getQueryLog())->pluck('query')->implode("\n"))->not->toContain('r_report_schedules');
});

it('schedules active reports when a schedule command starts, once', function (): void {
    $active = ReportSchedule::factory()->create();
    ReportSchedule::factory()->inactive()->create();

    startCommand('schedule:run');
    startCommand('schedule:list');

    expect(scheduledReportCommands())->toHaveCount(1)
        ->and(scheduledReportCommands()[0])->toContain("reports:run {$active->report_id}")
        ->and(app(Schedule::class)->events())->not->toBeEmpty();
});
