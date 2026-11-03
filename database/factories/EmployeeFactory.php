<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'employee_code' => fake()->unique()->bothify('EMP-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_number' => fake()->numerify('#######'),
            'phone' => fake()->numerify('7#######'),
            'hire_date' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
            'status' => EmployeeStatus::Active,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Inactive,
        ]);
    }
}
