<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\User;

class InventoryMovementPolicy
{
    public function verInventario(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ViewInventory, $branch->id);
    }

    /**
     * Entries, exits and internal consumptions.
     */
    public function registrar(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::RegisterInventoryMovements, $branch->id);
    }

    public function ajustar(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::AdjustInventory, $branch->id);
    }
}
