<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function devolver(User $user, Payment $payment): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::RefundPayments, $payment->stay->branch_id);
    }
}
