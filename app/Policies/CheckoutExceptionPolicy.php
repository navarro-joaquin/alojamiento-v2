<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\CheckoutException;
use App\Models\User;

/**
 * The requester can never approve their own request, even holding the
 * approval permission (CheckoutExceptionService enforces it too).
 */
class CheckoutExceptionPolicy
{
    public function verPendientes(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ApproveCheckoutExceptions, $branch->id);
    }

    public function aprobar(User $user, CheckoutException $checkoutException): bool
    {
        return $checkoutException->requested_by !== $user->id
            && $user->tienePermisoEnSucursal(BranchPermission::ApproveCheckoutExceptions, $checkoutException->stay->branch_id);
    }

    public function rechazar(User $user, CheckoutException $checkoutException): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ApproveCheckoutExceptions, $checkoutException->stay->branch_id);
    }
}
