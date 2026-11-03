<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\CashRegister;
use App\Models\User;

class CashRegisterPolicy
{
    public function abrirSesion(User $user, CashRegister $cashRegister): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::OpenCashSessions, $cashRegister->branch_id);
    }
}
