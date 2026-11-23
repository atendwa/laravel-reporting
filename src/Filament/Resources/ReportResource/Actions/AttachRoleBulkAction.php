<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Collection;
use Reporting\Models\Report;
use Reporting\Support\Access;
use Spatie\Permission\Models\Role;

final class AttachRoleBulkAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Attach role')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->modalDescription('Adds the role to every selected report. Roles already on a report stay as they are.')
            ->visible(fn (): bool => Access::isAdministrator())
            ->schema([
                Select::make('role_id')->label('Role')->required()->searchable()
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all()),
                Toggle::make('can_access_all_tenants')->label('Can run these reports across all teams')->default(false),
            ])
            ->action(fn (Collection $records, array $data) => $this->attach($records, $data))
            ->successNotificationTitle('Role attached to the selected reports.')
            ->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): string
    {
        return 'attach_role';
    }

    /**
     * @param  Collection<int, Report>  $records
     * @param  array{role_id: int|string, can_access_all_tenants?: bool}  $data
     */
    private function attach(Collection $records, array $data): void
    {
        $records->each(fn (Report $report): array => $report->roles()->syncWithoutDetaching([
            (int) $data['role_id'] => ['can_access_all_tenants' => (bool) ($data['can_access_all_tenants'] ?? false)],
        ]));

        $this->success();
    }
}
