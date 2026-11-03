<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\CashSession;
use App\Models\User;

class CashSessionPolicy
{
    public function ver(User $user, CashSession $cashSession): bool
    {
        $branchId = $cashSession->cashRegister->branch_id;

        return $user->tienePermisoEnSucursal(BranchPermission::OpenCashSessions, $branchId)
            || $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $branchId);
    }

    public function cerrar(User $user, CashSession $cashSession): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $cashSession->cashRegister->branch_id);
    }
}
