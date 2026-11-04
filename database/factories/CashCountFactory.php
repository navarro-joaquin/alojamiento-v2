<?php

namespace Database\Factories;

use App\Enums\CashCountType;
use App\Models\CashCount;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCount>
 */
class CashCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'counted_by' => User::factory(),
            'count_type' => CashCountType::Partial,
            'expected_amount' => '200.00',
            'declared_amount' => '200.00',
            'difference_amount' => '0.00',
            'counted_at' => now(),
        ];
    }
}
