<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\InventoryTransfer;
use App\Models\User;

/**
 * The source branch creates, sends and cancels a transfer; the destination
 * branch receives it.
 */
class InventoryTransferPolicy
{
    public function verTransferencias(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::TransferInventory, $branch->id);
    }

    public function crear(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::TransferInventory, $branch->id);
    }

    public function enviar(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::TransferInventory, $inventoryTransfer->fromWarehouse->branch_id);
    }

    public function cancelar(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::TransferInventory, $inventoryTransfer->fromWarehouse->branch_id);
    }

    public function recibir(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::TransferInventory, $inventoryTransfer->toWarehouse->branch_id);
    }
}
