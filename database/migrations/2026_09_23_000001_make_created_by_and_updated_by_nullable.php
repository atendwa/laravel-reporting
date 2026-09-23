<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;
use Reporting\Models\ReportingSchedule;

return new class extends Migration
{
    /**
     * @var array<class-string<Illuminate\Database\Eloquent\Model>>
     */
    private array $models = [Report::class, ReportingSchedule::class, ReportHistory::class];

    /**
     * created_by/updated_by were originally created NOT NULL with no default, but nothing in the
     * package (commands, Filament create pages, seeders) ever populates them - every insert failed
     * under strict SQL mode. Drop and re-add as nullable rather than ->change() to avoid a
     * doctrine/dbal dependency; every existing row already failed to be created with these columns
     * set to anything meaningful, so there is nothing worth preserving.
     */
    public function up(): void
    {
        foreach ($this->models as $model) {
            $table = (new $model)->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['created_by', 'updated_by'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropIndex([$column]);
                    $blueprint->dropColumn($column);
                });
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger($column)->nullable()->index());
            }
        }
    }
};
