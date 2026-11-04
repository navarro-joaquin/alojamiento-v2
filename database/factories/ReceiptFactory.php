<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
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
            'folio_id' => fn (array $attributes) => Payment::find($attributes['payment_id'])->folio_id,
            'branch_id' => fn (array $attributes) => Payment::find($attributes['payment_id'])->folio->branch_id,
            'prefix' => 'SUC',
            'number' => fake()->unique()->numberBetween(1, 999999),
            'holder_name' => fake()->name(),
            'holder_document' => fake()->numerify('#######'),
            'amount' => fn (array $attributes) => Payment::find($attributes['payment_id'])->amount,
            'status' => ReceiptStatus::Issued,
            'issued_by' => User::factory(),
            'issued_at' => now(),
        ];
    }

    public function anulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceiptStatus::Voided,
            'voided_by' => User::factory(),
            'voided_at' => now(),
            'void_reason' => 'Datos del titular equivocados',
        ]);
    }
}
