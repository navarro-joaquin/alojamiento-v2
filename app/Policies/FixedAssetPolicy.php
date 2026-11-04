<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\User;

class FixedAssetPolicy
{
    public function verActivos(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ManageFixedAssets, $branch->id)
            || $user->tienePermisoEnSucursal(BranchPermission::RetireFixedAssets, $branch->id);
    }

    /**
     * Register, edit and move the assets of the branch.
     */
    public function gestionar(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ManageFixedAssets, $branch->id);
    }

    public function darDeBaja(User $user, FixedAsset $fixedAsset): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::RetireFixedAssets, $fixedAsset->branch_id);
    }
}
