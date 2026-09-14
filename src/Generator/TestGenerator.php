<?php

declare(strict_types=1);

namespace Reporting\Generator;

use Illuminate\Support\Collection;
use Reporting\Contracts\GeneratorInterface;

final class TestGenerator implements GeneratorInterface
{
    /**
     * @param  array<string, mixed>  $modifiers
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function generate(array $modifiers): Collection
    {
        $rows = collect([
            [
                'id' => 1,
                'name' => 'Item A',
                'category' => 'Alpha',
                'amount' => 100.50,
                'date' => '2026-01-15',
            ],
            [
                'id' => 2,
                'name' => 'Item B',
                'category' => 'Beta',
                'amount' => 250.00,
                'date' => '2026-01-20',
            ],
            [
                'id' => 3,
                'name' => 'Item C',
                'category' => 'Alpha',
                'amount' => 75.25,
                'date' => '2026-02-01',
            ],
        ]);

        if (isset($modifiers['category'])) {
            return $rows->where('category', $modifiers['category'])->values();
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getModifiers(): array
    {
        return [
            [
                'name' => 'date_from',
                'label' => 'Date From',
                'type' => 'date',
                'required' => false,
                'default' => null,
                'validation' => 'nullable|date',
                'options' => [],
            ],
            [
                'name' => 'date_to',
                'label' => 'Date To',
                'type' => 'date',
                'required' => false,
                'default' => null,
                'validation' => 'nullable|date',
                'options' => [],
            ],
            [
                'name' => 'category',
                'label' => 'Category',
                'type' => 'select',
                'required' => false,
                'default' => null,
                'validation' => 'nullable|string',
                'options' => [
                    'Alpha' => 'Alpha',
                    'Beta' => 'Beta',
                    'Gamma' => 'Gamma',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getHeaders(): array
    {
        return ['ID', 'Name', 'Category', 'Amount', 'Date'];
    }

    public function getName(): string
    {
        return 'Test Report';
    }

    public function getDescription(): string
    {
        return 'A simple test report for development and testing purposes.';
    }

    /**
     * @param  array<string, mixed>  $modifiers
     */
    public function validateModifiers(array $modifiers): bool
    {
        return true;
    }

    public function estimateExecutionTime(): int
    {
        return 60;
    }

    public function isHeavy(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'category' => null,
            'date_from' => null,
            'date_to' => null,
        ];
    }

    public function getGroupingConfig(): ?array
    {
        return null;
    }

    public function getGroup(): string
    {
        return 'Test Group';
    }
}
