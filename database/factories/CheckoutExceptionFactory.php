<?php

namespace Database\Factories;

use App\Enums\CheckoutExceptionStatus;
use App\Models\CheckoutException;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutException>
 */
class CheckoutExceptionFactory extends Factory
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
            'requested_by' => User::factory(),
            'outstanding_amount' => '250.00',
            'reason' => 'El huésped paga con transferencia mañana',
            'status' => CheckoutExceptionStatus::Requested,
            'requested_at' => now(),
        ];
    }

    public function aprobada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CheckoutExceptionStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(config('lodging.checkout_exception_minutes')),
        ]);
    }

    public function vencida(): static
    {
        return $this->aprobada()->state(fn (array $attributes) => [
            'approved_at' => now()->subHours(3),
            'expires_at' => now()->subMinute(),
        ]);
    }
}
