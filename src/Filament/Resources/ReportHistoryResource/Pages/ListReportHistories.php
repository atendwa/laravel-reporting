<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportHistoryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Reporting\Filament\Resources\ReportHistoryResource;

final class ListReportHistories extends ListRecords
{
    protected static string $resource = ReportHistoryResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return isSystemStaff() || auth()->user()?->hasRole('super_admin');
    }
}
