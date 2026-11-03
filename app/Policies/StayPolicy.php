<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Stay;
use App\Models\User;

/**
 * Checking out includes refunding the credit balance, so it does not
 * require the refund permission.
 */
class StayPolicy
{
    public function cambiarHabitacion(User $user, Stay $stay): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ChangeStayRooms, $stay->branch_id);
    }

    public function registrarSalida(User $user, Stay $stay): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CheckOutStays, $stay->branch_id);
    }

    public function registrarCargo(User $user, Stay $stay): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::AddCharges, $stay->branch_id);
    }

    public function registrarDescuento(User $user, Stay $stay): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::GrantDiscounts, $stay->branch_id);
    }

    public function cobrarPago(User $user, Stay $stay): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CollectPayments, $stay->branch_id);
    }
}
