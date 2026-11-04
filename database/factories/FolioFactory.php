<?php

namespace Database\Factories;

use App\Enums\FolioType;
use App\Models\Branch;
use App\Models\Folio;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stay folios are opened by the stay factory; this factory makes counter
 * folios unless told which stay the folio belongs to.
 *
 * @extends Factory<Folio>
 */
class FolioFactory extends Factory
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
            'folio_type' => FolioType::Counter,
            'stay_id' => null,
            'holder_name' => fake()->name(),
            'created_by' => User::factory(),
        ];
    }

    public function deEstadia(Stay $stay): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $stay->branch_id,
            'folio_type' => FolioType::Stay,
            'stay_id' => $stay->id,
            'holder_name' => null,
            'created_by' => $stay->created_by,
        ]);
    }
}
