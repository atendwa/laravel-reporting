<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Pages;

use BetaFilament\Overrides\ListRecords;
use Reporting\Filament\Resources\ReportResource;

final class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return isSystemStaff() || auth()->user()?->hasRole('super_admin');
    }
}
