<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state: a supply that is not sold to guests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_category_id' => null,
            'sku' => fake()->unique()->bothify('PRD-####'),
            'name' => fake()->words(2, true),
            'unit' => 'unidad',
            'sale_price' => null,
            'is_sellable' => false,
            'is_active' => true,
        ];
    }

    public function vendible(string $salePrice = '15.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_price' => $salePrice,
            'is_sellable' => true,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
