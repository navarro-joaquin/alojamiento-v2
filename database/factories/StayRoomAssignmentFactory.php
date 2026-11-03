<?php

namespace Database\Factories;

use App\Models\Stay;
use App\Models\StayRoomAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StayRoomAssignment>
 */
class StayRoomAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stay_id' => Stay::factory(),
            'room_id' => fn (array $attributes) => Stay::find($attributes['stay_id'])->room_id,
            'rate_amount' => fn (array $attributes) => Stay::find($attributes['stay_id'])->rate_amount,
            'assigned_from' => now(),
            'assigned_to' => null,
            'changed_by' => User::factory(),
            'reason' => null,
        ];
    }
}
