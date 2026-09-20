<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Support\Access;

final class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Access::isAdministrator();
    }

    /**
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
