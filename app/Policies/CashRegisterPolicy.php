<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\User;

class CashRegisterPolicy
{
    /**
     * Whoever opens or closes the registers of the branch may see them.
     */
    public function verCajas(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::OpenCashSessions, $branch->id)
            || $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $branch->id);
    }

    public function abrirSesion(User $user, CashRegister $cashRegister): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::OpenCashSessions, $cashRegister->branch_id);
    }
}
