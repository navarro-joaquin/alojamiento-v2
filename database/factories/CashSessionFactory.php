<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'opened_by' => User::factory(),
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'opening_amount' => '200.00',
            'status' => CashSessionStatus::Open,
        ];
    }

    public function enCierre(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashSessionStatus::Closing,
        ]);
    }

    public function cerrada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashSessionStatus::Closed,
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'expected_amount' => '200.00',
            'declared_amount' => '200.00',
            'difference_amount' => '0.00',
        ]);
    }
}
