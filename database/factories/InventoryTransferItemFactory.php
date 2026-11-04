<?php

namespace Database\Factories;

use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransferItem>
 */
class InventoryTransferItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_transfer_id' => InventoryTransfer::factory(),
            'product_id' => Product::factory(),
            'quantity' => '5.000',
            'unit_cost' => null,
            'received_quantity' => null,
        ];
    }
}
