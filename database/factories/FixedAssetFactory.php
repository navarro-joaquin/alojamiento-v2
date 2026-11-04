<?php

namespace Database\Factories;

use App\Enums\FixedAssetStatus;
use App\Models\Branch;
use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    /**
     * Define the model's default state: an asset in storage, without history.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'room_id' => null,
            'asset_code' => fake()->unique()->bothify('AF-#####'),
            'name' => fake()->randomElement(['Televisor 32"', 'Frigobar', 'Cama de dos plazas', 'Aire acondicionado']),
            'serial_number' => fake()->bothify('SN-########'),
            'purchase_date' => '2025-06-15',
            'purchase_cost' => '1800.00',
            'status' => FixedAssetStatus::InStorage,
        ];
    }

    public function dadoDeBaja(): static
    {
        return $this->state(fn (array $attributes) => [
            'room_id' => null,
            'status' => FixedAssetStatus::Retired,
        ]);
    }
}
