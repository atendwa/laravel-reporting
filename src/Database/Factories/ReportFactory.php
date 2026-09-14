<?php

declare(strict_types=1);

namespace Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Reporting\Models\Report;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generator_class' => 'App\\Reports\\Generators\\' . $this->faker->unique()->uuid() . 'Generator',
            'description' => $this->faker->sentence(),
            'group' => $this->faker->optional(0.3)->word(),
            'is_heavy' => $this->faker->boolean(20),
            'cooldown_minutes' => $this->faker->optional(0.3)->numberBetween(15, 120),
            'name' => $this->faker->words(3, asText: true),
            'orientation' => 'landscape',
            'generate_csv' => true,
            'generate_pdf' => true,
            'is_active' => true,
            'parameters' => [],
        ];
    }

    public function heavy(): self
    {
        return $this->state(['is_heavy' => true]);
    }

    public function csvOnly(): self
    {
        return $this->state(['generate_csv' => true, 'generate_pdf' => false]);
    }

    public function pdfOnly(): self
    {
        return $this->state(['generate_csv' => false, 'generate_pdf' => true]);
    }

    public function portrait(): self
    {
        return $this->state(['orientation' => 'portrait']);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
