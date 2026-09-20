<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Database\PluginMigration;
use Reporting\Models\Report;
use Reporting\Models\ReportingSchedule;
use Reporting\Models\ReportSchedule;

return new class extends PluginMigration
{
    protected string $model = ReportSchedule::class;

    public function up(): void
    {
        if (Schema::hasTable($this->getTableName())) {
            return;
        }

        Schema::create($this->getTableName(), function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignIdFor(Report::class)->index();
            $blueprint->foreignIdFor(ReportingSchedule::class)->index();
            $blueprint->boolean('is_active')->default(value: true);
            $blueprint->timestamps();

            $blueprint->unique(['report_id', 'reporting_schedule_id'], 'report_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }
};
