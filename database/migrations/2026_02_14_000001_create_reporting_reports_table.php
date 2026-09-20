<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Database\PluginMigration;
use Reporting\Models\Report;

return new class extends PluginMigration
{
    protected string $model = Report::class;

    public function up(): void
    {
        if (Schema::hasTable($this->getTableName())) {
            return;
        }

        Schema::create($this->getTableName(), function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name')->index();
            $blueprint->string('group')->nullable();
            $blueprint->text('description')->nullable();
            $blueprint->string('generator_class')->unique()->index();
            $blueprint->boolean('is_heavy')->default(value: false);
            $blueprint->json('parameters')->nullable();
            $blueprint->boolean('generate_csv')->default(value: true);
            $blueprint->boolean('generate_pdf')->default(value: true);
            $blueprint->string('orientation', 20)->default('landscape');

            $blueprint->string('group_rows_by')->nullable();
            $blueprint->string('group_columns_by')->nullable();
            $blueprint->string('group_value_column')->nullable();

            $blueprint->unsignedInteger('cooldown_minutes')->nullable();
            $this->auditColumns($blueprint);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }
};
