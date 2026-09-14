<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Reporting\Consts\Reporting;
use Reporting\Contracts\TenantAwareInterface;
use Reporting\Filament\Resources\ReportHistoryResource;
use Reporting\Filament\Resources\ReportingScheduleResource;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Models\Report;
use Reporting\Models\ReportingSchedule;
use Reporting\Services\GeneratorDiscovery;

final class SetupCommand extends Command
{
    protected $signature = Reporting::NAME . ':setup';

    protected $description = 'Set up the Reporting plugin: generate Shield permissions, seed schedules, '
        . 'and register generators';

    public function handle(GeneratorDiscovery $generatorDiscovery): int
    {
        $this->generateShieldPermissions();
        $this->seedSchedules();
        $this->registerGenerators($generatorDiscovery);

        $this->components->info('Reporting setup complete.');

        return self::SUCCESS;
    }

    private function generateShieldPermissions(): void
    {
        $this->components->info('Generating Shield permissions...');

        $resources = [
            ReportResource::class,
            ReportHistoryResource::class,
            ReportingScheduleResource::class,
        ];

        foreach ($resources as $resource) {
            $resourceName = class_basename($resource);

            Artisan::call('shield:generate', [
                '--ignore-existing-policies' => true,
                '--resource' => $resourceName,
                '--no-interaction' => true,
                '--panel' => 'app',
            ]);

            $this->components->info(sprintf('  Permissions generated for %s.', $resourceName));
        }
    }

    private function seedSchedules(): void
    {
        $this->components->info('Seeding default schedules...');

        /** @var array<int, array{name: string, cron_expression: string, description: string}> $schedules */
        $schedules = [
            [
                'name' => 'Daily',
                'cron_expression' => '0 8 * * *',
                'description' => 'Runs daily at 8:00 AM',
            ],
            [
                'name' => 'Weekly',
                'cron_expression' => '0 8 * * 1',
                'description' => 'Runs every Monday at 8:00 AM',
            ],
            [
                'name' => 'Monthly',
                'cron_expression' => '0 8 1 * *',
                'description' => 'Runs on the 1st of every month at 8:00 AM',
            ],
            [
                'name' => 'Quarterly',
                'cron_expression' => '0 8 1 1,4,7,10 *',
                'description' => 'Runs on the 1st of Jan, Apr, Jul, Oct at 8:00 AM',
            ],
        ];

        foreach ($schedules as $schedule) {
            ReportingSchedule::query()->firstOrCreate(
                ['name' => $schedule['name']],
                [
                    'cron_expression' => $schedule['cron_expression'],
                    'description' => $schedule['description'],
                    'timezone' => config('app.timezone', 'UTC'),
                    'is_active' => true,
                ],
            );
        }

        $this->components->info('  Default schedules seeded.');
    }

    private function registerGenerators(GeneratorDiscovery $generatorDiscovery): void
    {
        $this->components->info('Auto-registering discovered generators...');

        $options = $generatorDiscovery->getGeneratorOptions();

        if (blank($options)) {
            $this->components->warn('  No generators found. Check your generator paths in config/reporting.php.');

            return;
        }

        $registered = 0;

        foreach ($options as $class => $name) {
            if (Report::query()->where('generator_class', $class)->exists()) {
                continue;
            }

            $generator = $generatorDiscovery->resolve($class);

            $groupingConfig = $generator->getGroupingConfig();

            Report::query()->create([
                'parameters' => $generator->getDefaultParameters(),
                'description' => $generator->getDescription(),
                'generator_class' => $class,
                'is_heavy' => $generator->isHeavy(),
                'name' => $generator->getName(),
                'is_active' => true,
                'group' => null,
                'group_rows_by' => $groupingConfig['group_rows_by'] ?? null,
                'group_columns_by' => $groupingConfig['group_columns_by'] ?? null,
                'group_value_column' => $groupingConfig['group_value_column'] ?? null,
            ]);

            ++$registered;

            $tenantLabel = $generator instanceof TenantAwareInterface ? ' [tenant-aware]' : '';
            $this->components->info(sprintf('  Registered: %s%s', $name, $tenantLabel));
        }

        if ($registered === 0) {
            $this->components->info('  All generators already registered.');
        } else {
            $this->components->info(sprintf('  %d generator(s) registered.', $registered));
        }
    }
}
