<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Support\Facades\Log;
use Reporting\Consts\ReportSource;
use Reporting\Consts\ReportStatus;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Events\ReportFailed;
use Reporting\Events\ReportGenerated;
use Reporting\Events\ReportGenerationStarted;
use Reporting\Exceptions\InvalidModifiersException;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Throwable;

final readonly class ReportExecutor
{
    public function __construct(
        private GeneratorDiscovery $generatorDiscovery,
        private FileGenerator $fileGenerator,
    ) {}

    /**
     * Execute a report generation cycle.
     *
     * @param  array<string, mixed>  $modifiers
     *
     * @throws Throwable
     */
    public function execute(
        Report $report,
        array $modifiers = [],
        ?int $userId = null,
        string $source = ReportSource::SOURCE_MANUAL,
    ): ReportHistory {
        $reportHistory = $this->createHistory($report, $modifiers, $userId, $source);

        try {
            $generator = $this->generatorDiscovery->resolve($report->generator_class);

            $this->validateModifiers($generator, $modifiers);
            $this->markAsProcessing($reportHistory);

            event(new ReportGenerationStarted($report, $reportHistory));

            $startTime = hrtime(as_number: true);
            $data = $generator->generate($modifiers);

            $executionTimeMs = $this->calculateExecutionTime($startTime);

            $files = $this->fileGenerator->generate($generator, $data, $modifiers, $report);

            $this->markAsCompleted($reportHistory, $files, $executionTimeMs);

            event(new ReportGenerated($report, $reportHistory));

            return $reportHistory;
        } catch (Throwable $throwable) {
            $this->markAsFailed($reportHistory, $throwable->getMessage());

            event(new ReportFailed($report, $reportHistory, $throwable));

            Log::error($throwable->getMessage(), ['exception' => $throwable]);

            throw $throwable;
        }
    }

    /**
     * Create the initial history record with pending status.
     *
     * @param  array<string, mixed>  $modifiers
     */
    private function createHistory(
        Report $report,
        array $modifiers,
        ?int $userId,
        string $source,
    ): ReportHistory {
        /** @var ReportHistory */
        return $report->histories()->create([
            'status' => ReportStatus::STATUS_PENDING,
            'triggered_by_user_id' => $userId,
            'modifiers' => $modifiers,
            'source' => $source,
        ]);
    }

    /**
     * Validate modifiers against the generator's rules.
     *
     * @param  array<string, mixed>  $modifiers
     */
    private function validateModifiers(
        GeneratorInterface $generator,
        array $modifiers,
    ): void {
        if (! $generator->validateModifiers($modifiers)) {
            throw InvalidModifiersException::forModifiers($modifiers);
        }
    }

    private function markAsProcessing(ReportHistory $reportHistory): void
    {
        $reportHistory->update([
            'status' => ReportStatus::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark history as completed with file details and metrics.
     *
     * @param  array{csv_path: ?string, pdf_path: ?string, file_size: int, row_count: int}  $files
     */
    private function markAsCompleted(
        ReportHistory $reportHistory,
        array $files,
        int $executionTimeMs,
    ): void {
        $reportHistory->update([
            'status' => ReportStatus::STATUS_COMPLETED,
            'execution_time_ms' => $executionTimeMs,
            'csv_file_path' => $files['csv_path'],
            'pdf_file_path' => $files['pdf_path'],
            'file_size' => $files['file_size'],
            'row_count' => $files['row_count'],
            'completed_at' => now(),
        ]);
    }

    private function markAsFailed(ReportHistory $reportHistory, string $errorMessage): void
    {
        $reportHistory->update([
            'status' => ReportStatus::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Calculate execution time in milliseconds from hrtime.
     */
    private function calculateExecutionTime(int $startNano): int
    {
        return (int) ((hrtime(as_number: true) - $startNano) / 1_000_000);
    }
}
