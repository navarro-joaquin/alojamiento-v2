<?php

namespace Database\Factories;

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StayCharge>
 */
class StayChargeFactory extends Factory
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
            'folio_id' => fn (array $attributes) => Stay::find($attributes['stay_id'])->folio->id,
            'charge_type' => ChargeType::Lodging,
            'direction' => ChargeDirection::Debit,
            'description' => 'Noche de alojamiento',
            'service_date' => now()->toDateString(),
            'quantity' => '1.00',
            'unit_price' => '250.00',
            'total_amount' => '250.00',
            'status' => ChargeStatus::Confirmed,
            'created_by' => User::factory(),
        ];
    }

    public function anulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Voided,
            'voided_by' => User::factory(),
            'voided_at' => now(),
            'void_reason' => 'Cargo registrado por error',
        ]);
    }

    public function descuento(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_type' => ChargeType::Discount,
            'direction' => ChargeDirection::Credit,
            'description' => 'Descuento',
            'unit_price' => '50.00',
            'total_amount' => '50.00',
        ]);
    }
}
