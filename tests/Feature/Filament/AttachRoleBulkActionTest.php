<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Reporting\Database\Factories\ReportFactory;
use Reporting\Filament\Resources\ReportResource\Pages\ListReports;
use Reporting\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Support\Models\User;
use Support\Testing\FilamentTestHelpers;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel(TestCase::PANEL_ID));
});

it('attaches a role to every selected report, keeping the roles they already have', function (): void {
    FilamentTestHelpers::actingAsAuthorisedUser();
    $kept = Role::findOrCreate('Manager');
    $accountant = Role::findOrCreate('Accountant');
    $reports = ReportFactory::new()->count(3)->create();
    $reports->first()->roles()->attach($kept);

    Livewire::test(ListReports::class)
        ->callTableBulkAction('attach_role', $reports, ['role_id' => $accountant->id, 'can_access_all_tenants' => true])
        ->assertHasNoTableBulkActionErrors();

    foreach ($reports as $report) {
        expect($report->fresh()->roles->pluck('name'))->toContain('Accountant');
    }

    expect($reports->first()->fresh()->roles->pluck('name'))->toContain('Manager')
        ->and((bool) $reports->first()->fresh()->roles->firstWhere('name', 'Accountant')->pivot->can_access_all_tenants)->toBeTrue();
});

it('shows the action to super admins only', function (): void {
    $role = Role::findOrCreate('Reader');
    $user = User::query()->create(['name' => 'Reader', 'email' => 'reader@example.test', 'password' => 'secret']);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Report'));
    $user->assignRole($role);
    ReportFactory::new()->create()->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(ListReports::class)->assertTableBulkActionHidden('attach_role');
});
