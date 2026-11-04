<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;

/**
 * Whoever registered an expense can never approve it, even holding the
 * approval permission (ExpenseService enforces it too).
 */
class ExpensePolicy
{
    public function verGastos(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::RegisterExpenses, $branch->id)
            || $user->tienePermisoEnSucursal(BranchPermission::ApproveExpenses, $branch->id)
            || $user->tienePermisoEnSucursal(BranchPermission::VoidExpenses, $branch->id);
    }

    public function registrar(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::RegisterExpenses, $branch->id);
    }

    public function aprobar(User $user, Expense $expense): bool
    {
        return $expense->created_by !== $user->id
            && $user->tienePermisoEnSucursal(BranchPermission::ApproveExpenses, $expense->branch_id);
    }

    public function rechazar(User $user, Expense $expense): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ApproveExpenses, $expense->branch_id);
    }

    public function anular(User $user, Expense $expense): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::VoidExpenses, $expense->branch_id);
    }
}
