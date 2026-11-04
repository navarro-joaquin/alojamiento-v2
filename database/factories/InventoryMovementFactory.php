<?php

namespace Database\Factories;

use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state: an entry without a balance row, for
     * tests that only need a movement to exist.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'performed_by' => User::factory(),
            'movement_type' => InventoryMovementType::Entry,
            'direction' => InventoryMovementDirection::In,
            'quantity' => '10.000',
            'unit_cost' => '5.0000',
            'balance_after' => '10.000',
            'reference' => null,
            'notes' => null,
            'operation_uuid' => fake()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
