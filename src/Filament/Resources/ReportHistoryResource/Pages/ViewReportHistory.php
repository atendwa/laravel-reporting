<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportHistoryResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Reporting\Filament\Resources\ReportHistoryResource;

final class ViewReportHistory extends ViewRecord
{
    protected static string $resource = ReportHistoryResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return isSystemStaff() || auth()->user()?->hasRole('super_admin');
    }
}
