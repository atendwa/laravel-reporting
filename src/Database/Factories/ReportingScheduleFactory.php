<?php

declare(strict_types=1);

namespace Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Reporting\Models\ReportingSchedule;

/**
 * @extends Factory<ReportingSchedule>
 */
final class ReportingScheduleFactory extends Factory
{
    protected $model = ReportingSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, asText: true),
            'description' => $this->faker->sentence(),
            'timezone' => config('app.timezone'),
            'cron_expression' => '0 8 * * *',
            'is_active' => true,
        ];
    }

    public function daily(): self
    {
        return $this->state([
            'name' => 'Daily',
            'cron_expression' => '0 8 * * *',
        ]);
    }

    public function weekly(): self
    {
        return $this->state([
            'name' => 'Weekly',
            'cron_expression' => '0 8 * * 1',
        ]);
    }

    public function monthly(): self
    {
        return $this->state([
            'name' => 'Monthly',
            'cron_expression' => '0 8 1 * *',
        ]);
    }

    public function minutely(): self
    {
        return $this->state([
            'name' => 'Minutely',
            'cron_expression' => '* * * * *',
        ]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
