<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\Folio;
use App\Models\User;

/**
 * Counter sales: whoever charges and collects at the front desk sells; whoever
 * can both void charges and refund payments voids a sale.
 */
class FolioPolicy
{
    public function verVentasDeMostrador(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CollectPayments, $branch->id);
    }

    public function venderEnMostrador(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::AddCharges, $branch->id)
            && $user->tienePermisoEnSucursal(BranchPermission::CollectPayments, $branch->id);
    }

    public function anularVentaDeMostrador(User $user, Folio $folio): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::VoidCharges, $folio->branch_id)
            && $user->tienePermisoEnSucursal(BranchPermission::RefundPayments, $folio->branch_id);
    }
}
