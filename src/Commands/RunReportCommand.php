<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use Reporting\Consts\Reporting;
use Reporting\Jobs\GenerateReportJob;
use Reporting\Models\Report;
use Reporting\Utilities\CooldownChecker;

final class RunReportCommand extends Command
{
    protected $signature = Reporting::NAME . ':run
        {report : The report ID}
        {--user= : User ID who triggered the report}
        {--force : Skip lock check}
        {--modifiers= : JSON string of modifiers}';

    protected $description = 'Run a report generation job';

    public function handle(CooldownChecker $cooldownChecker): int
    {
        $report = $this->findReport();

        if (! $report instanceof Report) {
            error('Report not found.');

            return self::FAILURE;
        }

        if (! $report->is_active) {
            error('Report is not active.');

            return self::FAILURE;
        }

        $modifiers = $this->parseModifiers();

        if ($this->shouldCheckCooldown($report, $cooldownChecker)) {
            error('Report is currently on cooldown from a recent run.');

            return self::FAILURE;
        }

        $this->dispatchJob($report, $modifiers);

        info('Report ' . $report->name . ' has been queued for generation.');

        return self::SUCCESS;
    }

    private function findReport(): ?Report
    {
        return Report::query()->find($this->argument('report'));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseModifiers(): array
    {
        $raw = $this->option('modifiers');

        if (blank($raw)) {
            return [];
        }

        $decoded = json_decode((string) $raw, associative: true);

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }

    private function shouldCheckCooldown(Report $report, CooldownChecker $cooldownChecker): bool
    {
        if ($this->option('force')) {
            return false;
        }

        return $report->is_heavy && $cooldownChecker->isOnCooldown($report);
    }

    /**
     * @param  array<string, mixed>  $modifiers
     */
    private function dispatchJob(Report $report, array $modifiers): void
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        GenerateReportJob::dispatch($report, $modifiers, $userId);
    }
}
