<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUC-###'),
            'name' => 'Sucursal '.fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('7#######'),
            'timezone' => 'America/La_Paz',
            'current_business_date' => now()->toDateString(),
            'is_active' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
