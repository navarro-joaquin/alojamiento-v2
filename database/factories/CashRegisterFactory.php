<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CashRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
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
            'name' => 'Caja '.fake()->unique()->numerify('##'),
            'is_active' => true,
        ];
    }
}
