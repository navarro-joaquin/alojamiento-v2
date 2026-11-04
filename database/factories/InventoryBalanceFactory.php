<?php

namespace Database\Factories;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'quantity' => '10.000',
            'average_cost' => '5.0000',
        ];
    }
}
