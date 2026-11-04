<?php

namespace Database\Factories;

use App\Enums\FixedAssetStatus;
use App\Models\AssetMovement;
use App\Models\FixedAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    /**
     * Define the model's default state: the registration of the asset.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fixed_asset_id' => FixedAsset::factory(),
            'from_branch_id' => null,
            'to_branch_id' => fn (array $attributes) => FixedAsset::findOrFail($attributes['fixed_asset_id'])->branch_id,
            'from_room_id' => null,
            'to_room_id' => null,
            'from_status' => null,
            'to_status' => FixedAssetStatus::InStorage,
            'performed_by' => User::factory(),
            'moved_at' => now(),
            'notes' => null,
        ];
    }
}
