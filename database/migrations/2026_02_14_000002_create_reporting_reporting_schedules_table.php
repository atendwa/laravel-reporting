<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Database\PluginMigration;
use Reporting\Models\ReportingSchedule;

return new class extends PluginMigration
{
    protected string $model = ReportingSchedule::class;

    public function up(): void
    {
        if (Schema::hasTable($this->getTableName())) {
            return;
        }

        Schema::create($this->getTableName(), function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name')->index();
            $blueprint->text('description')->nullable();
            $blueprint->string('cron_expression');
            $blueprint->string('timezone')->default(config('app.timezone'));
            $this->auditColumns($blueprint);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }
};
