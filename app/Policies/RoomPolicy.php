<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function registrarIngreso(User $user, Room $room): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::CheckInStays, $room->branch_id);
    }

    public function cambiarEstadoLimpieza(User $user, Room $room): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::UpdateHousekeepingStatus, $room->branch_id);
    }

    public function cambiarEstadoOperativo(User $user, Room $room): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::UpdateOperationalStatus, $room->branch_id);
    }
}
