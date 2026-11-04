<?php

namespace App\Models;

use App\Enums\FixedAssetStatus;
use Database\Factories\FixedAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A durable item of a branch (furniture, appliances, equipment). Its
 * location and status only change through FixedAssetService, which keeps
 * the history in asset_movements.
 */
#[Fillable(['branch_id', 'room_id', 'asset_code', 'name', 'serial_number', 'purchase_date', 'purchase_cost', 'status'])]
class FixedAsset extends Model
{
    /** @use HasFactory<FixedAssetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'status' => FixedAssetStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<AssetMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }
}
