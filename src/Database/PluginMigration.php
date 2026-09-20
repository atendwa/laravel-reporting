<?php

declare(strict_types=1);

namespace Reporting\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

abstract class PluginMigration extends Migration
{
    /**
     * @var class-string<Model>
     */
    protected string $model;

    protected function getTableName(): string
    {
        return (new $this->model)->getTable();
    }

    protected function auditColumns(Blueprint $blueprint): void
    {
        $blueprint->boolean('is_active')->default(true)->index();
        $blueprint->string('location')->nullable();
        $blueprint->text('user_agent')->nullable();
        $blueprint->ipAddress()->nullable();

        foreach (['created' => false, 'updated' => false, 'deleted' => true, 'restored' => true, 'archived' => true] as $name => $nullable) {
            $user = $blueprint->unsignedBigInteger($name . '_by')->index();
            $time = $blueprint->timestamp($name . '_at');

            if ($nullable) {
                $user->nullable();
                $time->nullable();
            }
        }
    }
}
