<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Reporting\Models\Report;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'reporting_report_role';

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create('reporting_report_role', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignIdFor(Report::class)->index();
            $blueprint->foreignId('role_id')->index()->constrained('roles');

            $blueprint->boolean('can_access_all_tenants')
                ->default(value: false)
                ->comment(
                    'When true, users in this role may generate this report across all teams, not just their own.'
                );

            $blueprint->unique(['report_id', 'role_id']);
        });
    }

    public function down(): void
    {
        $table = 'reporting_report_role';

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::dropIfExists($table);
    }
};
