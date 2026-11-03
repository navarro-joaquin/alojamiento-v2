<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Enums\RoomStatusField;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Room state transitions. Every change is recorded in room_status_logs.
 */
class HousekeepingService
{
    public function cambiarEstadoLimpieza(Room $room, HousekeepingStatus $housekeepingStatus, User $user, ?string $reason = null): Room
    {
        return $this->cambiarEstado($room, RoomStatusField::Housekeeping, $housekeepingStatus, $user, $reason);
    }

    public function cambiarEstadoOperativo(Room $room, OperationalStatus $operationalStatus, User $user, ?string $reason = null): Room
    {
        return $this->cambiarEstado($room, RoomStatusField::Operational, $operationalStatus, $user, $reason);
    }

    private function cambiarEstado(Room $room, RoomStatusField $field, HousekeepingStatus|OperationalStatus $newStatus, User $user, ?string $reason): Room
    {
        return DB::transaction(function () use ($room, $field, $newStatus, $user, $reason): Room {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            $currentStatus = $lockedRoom->{$field->value};

            if ($currentStatus === $newStatus) {
                return $lockedRoom;
            }

            $lockedRoom->update([$field->value => $newStatus]);

            $lockedRoom->statusLogs()->create([
                'field' => $field,
                'old_value' => $currentStatus->value,
                'new_value' => $newStatus->value,
                'changed_by' => $user->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            return $lockedRoom;
        });
    }
}
