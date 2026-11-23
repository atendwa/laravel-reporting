<?php

declare(strict_types=1);

use Reporting\Database\Factories\ReportFactory;
use Reporting\Filament\Resources\ReportHistoryResource;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Filament\Resources\ReportResource\Pages\EditReport;
use Reporting\Filament\Resources\ReportResource\Pages\ListReports;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Support\Models\User;

function reportingUser(string ...$permissions): User
{
    $user = User::query()->create(['name' => 'Reader', 'email' => uniqid() . '@example.test', 'password' => 'secret']);
    collect($permissions)->each(fn (string $permission) => $user->givePermissionTo(Permission::findOrCreate($permission)));

    return $user;
}

it('opens reports to someone with the permission and a report linked to one of their roles', function (): void {
    $user = reportingUser('ViewAny:Report', 'ViewAny:ReportHistory');
    $role = Role::findOrCreate('Accountant');
    $user->assignRole($role);
    ReportFactory::new()->create()->roles()->attach($role);
    $this->actingAs($user);

    expect(ReportResource::canAccess())->toBeTrue()
        ->and(ListReports::canAccess())->toBeTrue()
        ->and(ReportHistoryResource::canAccess())->toBeTrue()
        ->and(EditReport::canAccess())->toBeFalse();
});

it('keeps reports closed to someone without the permission, even with a report linked to their role', function (): void {
    $user = reportingUser();
    $role = Role::findOrCreate('Accountant');
    $user->assignRole($role);
    ReportFactory::new()->create()->roles()->attach($role);
    $this->actingAs($user);

    expect(ReportResource::canAccess())->toBeFalse()
        ->and(ReportHistoryResource::canAccess())->toBeFalse();
});

it('keeps reports closed to someone with the permission but no report linked to their roles', function (): void {
    $user = reportingUser('ViewAny:Report', 'ViewAny:ReportHistory');
    $user->assignRole(Role::findOrCreate('Accountant'));
    ReportFactory::new()->create();
    $this->actingAs($user);

    expect(ReportResource::canAccess())->toBeFalse()
        ->and(ReportHistoryResource::canAccess())->toBeFalse();
});

it('still opens everything to a super admin', function (): void {
    $user = reportingUser();
    $user->assignRole(Role::findOrCreate('super_admin'));
    $this->actingAs($user);

    expect(ReportResource::canAccess())->toBeTrue()->and(EditReport::canAccess())->toBeTrue();
});
