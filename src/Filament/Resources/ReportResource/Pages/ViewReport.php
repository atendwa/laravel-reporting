<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Models\Report;
use Reporting\Support\Access;

final class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Access::allows('viewAny', Report::class);
    }
}
