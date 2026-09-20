<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Database\PluginMigration;
use Reporting\Models\Report;
use Reporting\Models\ReportHistory;

return new class extends PluginMigration
{
    protected string $model = ReportHistory::class;

    public function up(): void
    {
        if (Schema::hasTable($this->getTableName())) {
            return;
        }

        Schema::create($this->getTableName(), function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignIdFor(Report::class)->index();
            $blueprint->string('status')->default('pending')->index();
            $blueprint->string('csv_file_path')->nullable();
            $blueprint->string('pdf_file_path')->nullable();
            $blueprint->unsignedBigInteger('file_size')->nullable();
            $blueprint->unsignedInteger('row_count')->nullable();
            $blueprint->unsignedInteger('execution_time_ms')->nullable();
            $blueprint->string('source');
            $blueprint->unsignedBigInteger('triggered_by_user_id')->nullable();
            $blueprint->json('modifiers')->nullable();
            $blueprint->timestamp('started_at')->nullable();
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->text('error_message')->nullable();
            $this->auditColumns($blueprint);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }
};
