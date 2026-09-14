<?php

declare(strict_types=1);

namespace Reporting\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\FilamentManager;
use Filament\Panel;
use Reporting\Filament\Resources\ReportHistoryResource;
use Reporting\Filament\Resources\ReportingScheduleResource;
use Reporting\Filament\Resources\ReportResource;

final class ReportingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'reporting-plugin';
    }

    public static function make(): self
    {
        return new self;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverClusters(__DIR__ . '/../Clusters', 'Reporting\\Filament\\Clusters')
            ->resources([
                ReportingScheduleResource::class,
                ReportHistoryResource::class,
                ReportResource::class,
            ]);
    }

    public static function get(): Plugin|FilamentManager
    {
        return filament((new self)->getId());
    }

    public function canAccess(): bool
    {
        return true;
    }

    public function boot(Panel $panel): void {}
}
