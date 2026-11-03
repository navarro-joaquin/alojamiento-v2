<?php

namespace Database\Seeders;

use App\Enums\BranchPermission;
use App\Enums\BranchRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions and the global roles (without branch) and syncs
 * each role with its permissions. Safe to run again after adding cases.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        foreach (BranchPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        // The roles look permissions up in the cache, which still holds the list from before they were created.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (BranchRole::cases() as $role) {
            Role::findOrCreate($role->value)->syncPermissions(
                array_map(fn (BranchPermission $permission): string => $permission->value, $role->permisos()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
