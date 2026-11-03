<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomRate>
 */
class RoomRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'room_type_id' => RoomType::factory(),
            'name' => 'Tarifa estándar',
            'amount' => '250.00',
            'occupancy' => null,
            'days_of_week' => null,
            'priority' => 0,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
        ];
    }
}
