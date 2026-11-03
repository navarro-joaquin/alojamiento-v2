<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'cash_session_id' => fn (array $attributes) => Payment::find($attributes['payment_id'])->cash_session_id,
            'processed_by' => User::factory(),
            'amount' => '50.00',
            'refund_method' => PaymentMethod::Cash,
            'status' => RefundStatus::Confirmed,
            'reference' => null,
            'reason' => 'Devolución de saldo a favor',
            'operation_uuid' => (string) Str::uuid(),
            'request_hash' => hash('sha256', Str::random(32)),
            'refunded_at' => now(),
        ];
    }
}
