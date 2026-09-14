<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Reporting\Filament\Resources\ReportingScheduleResource;
use Throwable;

final class SchedulesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'schedules';

    /**
     * @throws Throwable
     */
    public function table(Table $table): Table
    {
        return ReportingScheduleResource::table($table)->headerActions([
            AttachAction::make()
                ->schema(fn (AttachAction $attachAction): array => [
                    $attachAction->getRecordSelect(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(state: true),
                ]),
        ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
