<?php

declare(strict_types=1);

namespace Support\Testing;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Support\Models\User;

class FilamentTestHelpers
{
    /**
     * Creates and logs in a user with every permission the reporting-plugin resources check for,
     * so Filament resource tests can exercise every page without tripping their policies.
     */
    public static function actingAsAuthorisedUser(): User
    {
        $user = User::query()->create([
            'name' => 'Reporting Test User',
            'email' => 'reporting-tester@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->givePermissionTo(self::allResourcePermissions());
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        Auth::login($user);

        return $user;
    }

    /**
     * @return array<int, Permission>
     */
    private static function allResourcePermissions(): array
    {
        $abilities = [
            'ViewAny', 'View', 'Create', 'Update', 'Delete',
            'DeleteAny', 'ForceDelete', 'ForceDeleteAny', 'Restore', 'RestoreAny', 'Replicate',
        ];
        $resources = ['Report', 'ReportingSchedule', 'ReportHistory'];

        $permissions = [];

        foreach ($resources as $resource) {
            foreach ($abilities as $ability) {
                $permissions[] = Permission::query()->firstOrCreate([
                    'name' => $ability . ':' . $resource,
                    'guard_name' => 'web',
                ]);
            }
        }

        return $permissions;
    }
}
