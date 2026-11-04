<?php

namespace Database\Factories;

use App\Enums\StayStatus;
use App\Models\Folio;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stay>
 */
class StayFactory extends Factory
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
            'branch_id' => fn (array $attributes) => Room::find($attributes['room_id'])->branch_id,
            'created_by' => User::factory(),
            'check_in_at' => now(),
            'expected_check_out_at' => now()->addDays(2),
            'check_out_at' => null,
            'status' => StayStatus::Active,
            'rate_amount' => '250.00',
            'adults' => 1,
            'children' => 0,
        ];
    }

    /**
     * Every stay has its own folio, as the check-in opens it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Stay $stay): void {
            Folio::factory()->deEstadia($stay)->create();
        });
    }

    public function finalizada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StayStatus::Completed,
            'check_out_at' => now(),
        ]);
    }
}
