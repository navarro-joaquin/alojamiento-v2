<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;

/**
 * Whoever collects payments issues their receipts; voiding one is reserved
 * to supervisors and admins.
 */
class ReceiptPolicy
{
    public function emitir(User $user, Payment $payment): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CollectPayments, $payment->folio->branch_id);
    }

    public function ver(User $user, Receipt $receipt): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CollectPayments, $receipt->branch_id)
            || $user->tienePermisoEnSucursal(BranchPermission::VoidReceipts, $receipt->branch_id);
    }

    public function anular(User $user, Receipt $receipt): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::VoidReceipts, $receipt->branch_id);
    }
}
