<?php

namespace Database\Factories;

use App\Enums\HousekeepingStatus;
use App\Enums\RoomStatusField;
use App\Models\Room;
use App\Models\RoomStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomStatusLog>
 */
class RoomStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'field' => RoomStatusField::Housekeeping,
            'old_value' => HousekeepingStatus::Clean->value,
            'new_value' => HousekeepingStatus::Dirty->value,
            'changed_by' => User::factory(),
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
