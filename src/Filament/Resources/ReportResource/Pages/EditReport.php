<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Reporting\Filament\Resources\ReportResource;

final class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return isSystemStaff() || auth()->user()?->hasRole('super_admin');
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
