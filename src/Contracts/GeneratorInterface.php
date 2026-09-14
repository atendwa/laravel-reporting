<?php

declare(strict_types=1);

namespace Reporting\Contracts;

use Illuminate\Support\Collection;

interface GeneratorInterface
{
    /**
     * Generate the report data with the given modifiers.
     *
     * @param  array<string, mixed>  $modifiers
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function generate(array $modifiers): Collection;

    /**
     * Define the dynamic form fields (modifiers) for this generator.
     *
     * Each modifier has: name, label, type, required, default, validation, options.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModifiers(): array;

    /**
     * Get the column headers for the report output.
     *
     * @return array<int, string>
     */
    public function getHeaders(): array;

    /**
     * Get the human-readable name of this report generator.
     */
    public function getName(): string;

    /**
     * Get the human-readable group of this report generator.
     */
    public function getGroup(): string;

    /**
     * Get a description of what this generator produces.
     */
    public function getDescription(): string;

    /**
     * Validate the given modifiers against this generator's rules.
     *
     * @param  array<string, mixed>  $modifiers
     */
    public function validateModifiers(array $modifiers): bool;

    /**
     * Estimate execution time in seconds, or null if unknown.
     */
    public function estimateExecutionTime(): int;

    /**
     * Whether this generator is resource-intensive and needs locking.
     */
    public function isHeavy(): bool;

    /**
     * Get the default parameters for this generator.
     *
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array;

    /**
     * Get the cross-tab grouping configuration, or null if the report is flat.
     *
     * @return array{group_rows_by: string, group_columns_by: string, group_value_column: string}|null
     */
    public function getGroupingConfig(): ?array;
}
