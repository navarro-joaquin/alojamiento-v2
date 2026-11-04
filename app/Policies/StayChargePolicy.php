<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\StayCharge;
use App\Models\User;

class StayChargePolicy
{
    public function anular(User $user, StayCharge $stayCharge): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::VoidCharges, $stayCharge->folio->branch_id);
    }
}
