<?php

namespace Database\Factories;

use App\Enums\InventoryTransferStatus;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransfer>
 */
class InventoryTransferFactory extends Factory
{
    /**
     * Define the model's default state: a draft without items.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => InventoryTransferStatus::Draft,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
