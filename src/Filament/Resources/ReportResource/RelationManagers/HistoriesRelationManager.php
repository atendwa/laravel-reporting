<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Reporting\Filament\Resources\ReportHistoryResource;

final class HistoriesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'histories';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return ReportHistoryResource::table($table);
    }
}
