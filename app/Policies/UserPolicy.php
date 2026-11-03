<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\User;

/**
 * User accounts are global: only a super admin manages them (granted by
 * Gate::before). The staff of a branch is managed by whoever holds the
 * staff permission there, by assigning roles to existing accounts.
 */
class UserPolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }

    public function gestionarPersonal(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ManageStaff, $branch->id);
    }
}
