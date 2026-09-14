<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;
use Reporting\Consts\Reporting;
use Throwable;

final class ResetTablesCommand extends Command
{
    protected $signature = Reporting::NAME . ':plugin:reset {--force : Skip confirmation prompt}';

    protected $description = 'Reset Reporting plugin tables by truncating their contents';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $confirmed = confirm(
                'This will permanently delete all data from ' . Reporting::DISPLAY_NAME . ' tables. Continue?',
                default: false
            );

            if (! $confirmed) {
                warning('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $results = spin(
            fn (): array => $this->truncateTables(),
            'Resetting ' . Reporting::DISPLAY_NAME . ' tables...'
        );

        collect($results)->each(function (array $result): void {
            when(
                ! $result['success'],
                fn () => error(sprintf('✗ Failed: %s - %s', $result['table'], $result['error'] ?? 'Unknown error'))
            );
            when($result['success'], fn () => info('✓ Truncated: ' . $result['table']));
        });

        info(Reporting::DISPLAY_NAME . ' tables have been reset.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{table: string, success: bool, error?: string}>
     */
    private function truncateTables(): array
    {
        $results = [];

        Schema::disableForeignKeyConstraints();

        try {
            collect(array_reverse(Reporting::MODELS))
                ->each(function (string $modelClass) use (&$results): void {
                    /** @var Model $model */
                    $model = new $modelClass;
                    $table = $model->getTable();

                    try {
                        $model->newQuery()->truncate();
                        $results[] = ['table' => $table, 'success' => true];
                    } catch (Throwable $throwable) {
                        $results[] = [
                            'error' => $throwable->getMessage(),
                            'success' => false,
                            'table' => $table,
                        ];
                    }
                });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $results;
    }
}
