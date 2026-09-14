<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use Reporting\Consts\Reporting;
use Reporting\Contracts\TenantAwareInterface;
use Reporting\Models\Report;
use Reporting\Services\GeneratorDiscovery;

final class MakeReportFromGeneratorCommand extends Command
{
    protected $signature = Reporting::NAME . ':make:report-from-generator
                            {--all : Register all unregistered generators without prompting}
                            {--regenerate : Drop all reports and their histories, then re-register every generator}';

    protected $description = 'Interactively create Reports from discovered generators not yet in the database';

    public function handle(GeneratorDiscovery $generatorDiscovery): int
    {
        $allOptions = $generatorDiscovery->getGeneratorOptions();

        if (blank($allOptions)) {
            error('No generators found. Check your generator paths in config/reporting.php.');

            return self::FAILURE;
        }

        if ($this->option('regenerate')) {
            return $this->regenerate($generatorDiscovery, $allOptions);
        }

        $registered = Report::query()->pluck('generator_class')->flip();

        $unregistered = collect($allOptions)
            ->reject(fn (string $name, string $class): bool => $registered->has($class));

        $registeredCount = count($allOptions) - $unregistered->count();

        if ($registeredCount > 0) {
            note($registeredCount . ' generator(s) already have a report in the database and are excluded.');
        }

        if ($unregistered->isEmpty()) {
            info('All discovered generators are already registered.');

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $toRegister = $unregistered->keys();
        } else {
            /** @var array<int, string> $selected */
            $selected = multiselect(
                label: 'Select generators to register (' . $unregistered->count() . ' available):',
                options: $unregistered->toArray(),
                required: true,
                hint: 'Space to toggle, Enter to confirm.',
            );

            $toRegister = collect($selected);
        }

        $created = $this->createReports($generatorDiscovery, $toRegister);

        $this->printCreated($generatorDiscovery, $created);

        info('Next steps:');
        info('  - Attach schedules via the Filament UI');
        info('  - Run manually: php artisan reports:run {id}');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $allOptions
     */
    private function regenerate(GeneratorDiscovery $generatorDiscovery, array $allOptions): int
    {
        $existing = Report::query()->count();

        note('Dropping ' . $existing . ' report(s) and all associated histories...');

        Report::query()->each(function (Report $report): void {
            $report->histories()->forceDelete();
            $report->forceDelete();
        });

        $created = $this->createReports($generatorDiscovery, collect(array_keys($allOptions)));

        $this->printCreated($generatorDiscovery, $created);

        info('Next steps:');
        info('  - Attach schedules via the Filament UI');
        info('  - Run manually: php artisan reports:run {id}');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Report>  $created
     */
    private function printCreated(GeneratorDiscovery $generatorDiscovery, Collection $created): void
    {
        $created->each(function (Report $report) use ($generatorDiscovery): void {
            info('✓ ' . $report->name . ' created (ID: ' . $report->id . ').');

            $generator = $generatorDiscovery->resolve($report->generator_class);

            if ($generator instanceof TenantAwareInterface) {
                info('  Tenant-aware: grant cross-tenant access via Reports → Allowed Roles.');
            }
        });
    }

    /**
     * @param  Collection<int, string>  $classes
     *
     * @return Collection<int, Report>
     */
    private function createReports(GeneratorDiscovery $generatorDiscovery, Collection $classes): Collection
    {
        return $classes->map(fn (string $class): Report => $this->createReport($generatorDiscovery, $class));
    }

    private function createReport(GeneratorDiscovery $generatorDiscovery, string $selectedClass): Report
    {
        $generator = $generatorDiscovery->resolve($selectedClass);
        $groupingConfig = $generator->getGroupingConfig();

        return Report::query()->create([
            'parameters' => $generator->getDefaultParameters(),
            'description' => $generator->getDescription(),
            'generator_class' => $selectedClass,
            'is_heavy' => $generator->isHeavy(),
            'name' => $generator->getName(),
            'created_by' => auth()->id(),
            'is_active' => true,
            'group' => $generator->getGroup(),
            'group_rows_by' => $groupingConfig['group_rows_by'] ?? null,
            'group_columns_by' => $groupingConfig['group_columns_by'] ?? null,
            'group_value_column' => $groupingConfig['group_value_column'] ?? null,
        ]);
    }
}
