<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportingScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Reporting\Filament\Resources\ReportingScheduleResource;

final class CreateReportingSchedule extends CreateRecord
{
    protected static string $resource = ReportingScheduleResource::class;

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
