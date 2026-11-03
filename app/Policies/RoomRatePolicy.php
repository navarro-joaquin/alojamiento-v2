<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\User;

class RoomRatePolicy
{
    public function gestionar(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ManageRoomRates, $branch->id);
    }
}
