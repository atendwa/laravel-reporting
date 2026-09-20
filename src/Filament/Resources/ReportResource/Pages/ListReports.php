<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Support\Access;

final class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Access::isAdministrator();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->button()
                ->action(fn () => $this->redirect(ReportResource::getUrl('index'))),
        ];
    }
}
