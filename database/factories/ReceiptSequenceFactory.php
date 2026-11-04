<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ReceiptSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptSequence>
 */
class ReceiptSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'prefix' => fn (array $attributes) => Branch::find($attributes['branch_id'])->code,
            'next_number' => 1,
        ];
    }
}
