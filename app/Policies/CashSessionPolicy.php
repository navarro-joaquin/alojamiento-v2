<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\User;

/**
 * Whoever closed a session can never review its difference, even holding the
 * review permission (CashService enforces it too).
 */
class CashSessionPolicy
{
    public function ver(User $user, CashSession $cashSession): bool
    {
        $branchId = $cashSession->cashRegister->branch_id;

        return $user->tienePermisoEnSucursal(BranchPermission::OpenCashSessions, $branchId)
            || $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $branchId)
            || $user->tienePermisoEnSucursal(BranchPermission::ReviewCashSessions, $branchId);
    }

    /**
     * A partial count is done by whoever may close the register.
     */
    public function registrarArqueo(User $user, CashSession $cashSession): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $cashSession->cashRegister->branch_id);
    }

    public function cerrar(User $user, CashSession $cashSession): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CloseCashSessions, $cashSession->cashRegister->branch_id);
    }

    public function verPendientesDeRevision(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ReviewCashSessions, $branch->id);
    }

    public function revisar(User $user, CashSession $cashSession): bool
    {
        return $cashSession->closed_by !== $user->id
            && $user->tienePermisoEnSucursal(BranchPermission::ReviewCashSessions, $cashSession->cashRegister->branch_id);
    }
}
