<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => DocumentType::Ci,
            'document_number' => fake()->unique()->numerify('########'),
            'document_complement' => null,
            'issuing_country' => 'BO',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'nationality' => 'BO',
            'birth_date' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'phone' => fake()->numerify('7#######'),
            'email' => fake()->safeEmail(),
        ];
    }

    public function extranjero(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => DocumentType::Passport,
            'document_number' => fake()->unique()->bothify('??######'),
            'issuing_country' => 'AR',
            'nationality' => 'AR',
        ]);
    }
}
