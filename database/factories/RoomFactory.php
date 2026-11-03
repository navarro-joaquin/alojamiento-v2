<?php

namespace Database\Factories;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Models\Branch;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
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
            'room_number' => fake()->unique()->numerify('###'),
            'floor' => (string) fake()->numberBetween(1, 5),
            'capacity' => 2,
            'housekeeping_status' => HousekeepingStatus::Clean,
            'operational_status' => OperationalStatus::InService,
        ];
    }

    public function sucia(): static
    {
        return $this->state(fn (array $attributes) => [
            'housekeeping_status' => HousekeepingStatus::Dirty,
        ]);
    }

    public function enMantenimiento(): static
    {
        return $this->state(fn (array $attributes) => [
            'operational_status' => OperationalStatus::Maintenance,
        ]);
    }
}
