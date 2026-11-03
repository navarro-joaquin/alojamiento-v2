<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('holds a role only in the branch where it was assigned', function () {
    $assignedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create();
    Role::create(['name' => 'supervisor', 'guard_name' => 'web']);

    setPermissionsTeamId($assignedBranch->id);
    $user->assignRole('supervisor');

    setPermissionsTeamId($otherBranch->id);
    $user->unsetRelation('roles');

    expect($user->hasRole('supervisor'))->toBeFalse();

    setPermissionsTeamId($assignedBranch->id);
    $user->unsetRelation('roles');

    expect($user->hasRole('supervisor'))->toBeTrue();
});
