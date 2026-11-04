<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folio_id' => fn () => Stay::factory()->create()->folio->id,
            'cash_session_id' => fn (array $attributes) => CashSession::factory()->for(
                CashRegister::factory()->state(['branch_id' => Folio::find($attributes['folio_id'])->branch_id])
            ),
            'created_by' => User::factory(),
            'payment_method' => PaymentMethod::Cash,
            'payment_type' => PaymentType::Settlement,
            'amount' => '250.00',
            'reference' => null,
            'operation_uuid' => (string) Str::uuid(),
            'request_hash' => hash('sha256', Str::random(32)),
            'paid_at' => now(),
            'status' => PaymentStatus::Confirmed,
        ];
    }

    public function anticipo(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => PaymentType::Deposit,
        ]);
    }

    public function conTarjeta(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Card,
            'reference' => fake()->numerify('AUT-######'),
        ]);
    }
}
