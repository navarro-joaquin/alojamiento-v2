<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A confirmed expense paid by bank transfer, so it needs no cash session.
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'expense_category_id' => ExpenseCategory::factory(),
            'created_by' => User::factory(),
            'description' => 'Compra de productos de limpieza',
            'amount' => '80.00',
            'payment_method' => PaymentMethod::BankTransfer,
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::Confirmed,
            'operation_uuid' => (string) Str::uuid(),
            'request_hash' => hash('sha256', Str::random(32)),
        ];
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => '900.00',
            'status' => ExpenseStatus::PendingApproval,
        ]);
    }

    public function enEfectivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Cash,
        ]);
    }
}
