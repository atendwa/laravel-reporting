<?php

declare(strict_types=1);

namespace Reporting\Filament\Resources\ReportResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Reporting\Models\Report;
use Spatie\Permission\Models\Role;

final class RolesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'roles';

    protected static ?string $title = 'Allowed Roles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('guard_name'),

                IconColumn::make('pivot.can_access_all_tenants')
                    ->label('Cross-Tenant Access')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('warning')
                    ->tooltip(
                        'When enabled, users in this role can generate this report across all teams, '
                        . 'not just their own.'
                    ),
            ])
            ->headerActions([
                Action::make('attach')
                    ->label('Attach roles')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(fn (): array => $this->getAvailableRoles())

                            ->required(),

                        Toggle::make('can_access_all_tenants')
                            ->label('Cross-Tenant Access')
                            ->helperText(
                                'Allow users in these roles to generate this report across all teams, '
                                . 'not just their own.'
                            )
                            ->default(state: false),
                    ])
                    ->action(function (array $data): void {
                        /** @var Report $model */
                        $model = $this->getOwnerRecord();

                        $pivotData = ['can_access_all_tenants' => (bool) ($data['can_access_all_tenants'] ?? false)];

                        $model->roles()->attach(
                            array_fill_keys($data['roles'], $pivotData)
                        );

                        Notification::make()
                            ->title('Roles attached successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('toggleCrossTenantAccess')
                    ->label(fn (Role $role): string => $role->pivot->can_access_all_tenants
                        ? 'Revoke Cross-Tenant Access'
                        : 'Grant Cross-Tenant Access')
                    ->icon(fn (Role $role): string => $role->pivot->can_access_all_tenants
                        ? 'heroicon-o-lock-closed'
                        : 'heroicon-o-globe-alt')
                    ->color(fn (Role $role): string => $role->pivot->can_access_all_tenants
                        ? 'warning'
                        : 'gray')
                    ->requiresConfirmation()
                    ->action(function (Role $role): void {
                        /** @var Report $model */
                        $model = $this->getOwnerRecord();

                        $newValue = ! $role->pivot->can_access_all_tenants;

                        $model->roles()->updateExistingPivot($role->id, [
                            'can_access_all_tenants' => $newValue,
                        ]);

                        Notification::make()
                            ->title($newValue ? 'Cross-tenant access granted' : 'Cross-tenant access revoked')
                            ->success()
                            ->send();
                    }),

                DetachAction::make(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableRoles(): array
    {
        /** @var Report $model */
        $model = $this->getOwnerRecord();

        $existingRoleIds = $model->roles->pluck('id');

        return Role::query()
            ->whereNotIn('id', $existingRoleIds)
            ->pluck('name', 'id')
            ->all();
    }
}
