<?php

declare(strict_types=1);

namespace Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Reporting\Models\Report;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

/**
 * @extends Factory<ReportSchedule>
 */
final class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporting_schedule_id' => ReportingSchedule::factory(),
            'report_id' => Report::factory(),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
