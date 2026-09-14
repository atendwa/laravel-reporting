<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportingScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Reporting\Filament\Resources\ReportingScheduleResource;

final class CreateReportingSchedule extends CreateRecord
{
    protected static string $resource = ReportingScheduleResource::class;
}
