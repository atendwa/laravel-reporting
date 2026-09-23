<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportingScheduleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Reporting\Filament\Resources\ReportingScheduleResource;

final class EditReportingSchedule extends EditRecord
{
    protected static string $resource = ReportingScheduleResource::class;

    /**
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
