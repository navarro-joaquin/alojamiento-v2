<?php

use App\Enums\BranchPermission;
use App\Enums\BranchRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates global roles with the permissions of each role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBe(count(BranchPermission::cases()))
        ->and(Role::whereNotNull('branch_id')->exists())->toBeFalse();

    foreach (BranchRole::cases() as $role) {
        expect(Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($role->permisos())->pluck('value')->sort()->values()->all());
    }
});

it('restores the permissions of a role when run again', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Role::findByName(BranchRole::Receptionist->value)->givePermissionTo(BranchPermission::VoidCharges->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(BranchRole::cases()))
        ->and(Role::findByName(BranchRole::Receptionist->value)->hasPermissionTo(BranchPermission::VoidCharges->value))->toBeFalse();
});
