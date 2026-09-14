<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportingScheduleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Reporting\Filament\Resources\ReportingScheduleResource;

final class ListReportingSchedules extends ListRecords
{
    protected static string $resource = ReportingScheduleResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
