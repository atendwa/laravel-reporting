<?php

declare(strict_types=1);

namespace Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Reporting\Consts\ReportSource;
use Reporting\Consts\ReportStatus;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

/**
 * @extends Factory<ReportHistory>
 */
final class ReportHistoryFactory extends Factory
{
    protected $model = ReportHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'status' => ReportStatus::STATUS_PENDING,
            'source' => ReportSource::SOURCE_MANUAL,
            'modifiers' => [],
        ];
    }

    public function completed(): self
    {
        $startedAt = now()->subMinutes(5);
        $completedAt = now();

        $executionTimeMs = (int) $startedAt->diffInMilliseconds($completedAt);

        return $this->state([
            'file_size' => $this->faker->numberBetween(1024, 1048576),
            'row_count' => $this->faker->numberBetween(10, 10000),
            'csv_file_path' => 'reports/test-report.csv',
            'pdf_file_path' => 'reports/test-report.pdf',
            'status' => ReportStatus::STATUS_COMPLETED,
            'execution_time_ms' => $executionTimeMs,
            'completed_at' => $completedAt,
            'started_at' => $startedAt,
        ]);
    }

    public function failed(): self
    {
        return $this->state([
            'error_message' => $this->faker->sentence(),
            'started_at' => now()->subMinutes(2),
            'status' => ReportStatus::STATUS_FAILED,
        ]);
    }

    public function processing(): self
    {
        return $this->state([
            'status' => ReportStatus::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function scheduled(): self
    {
        return $this->state([
            'source' => ReportSource::SOURCE_SCHEDULED,
        ]);
    }

    public function triggeredBy(int $userId): self
    {
        return $this->state([
            'triggered_by_user_id' => $userId,
        ]);
    }
}
