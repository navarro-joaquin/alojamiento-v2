<?php

namespace App\Models;

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use Database\Factories\StayChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'folio_id',
    'stay_id',
    'charge_type',
    'direction',
    'description',
    'service_date',
    'quantity',
    'unit_price',
    'total_amount',
    'product_id',
    'inventory_movement_id',
    'status',
    'created_by',
    'voided_by',
    'voided_at',
    'void_reason',
])]
class StayCharge extends Model
{
    /** @use HasFactory<StayChargeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_type' => ChargeType::class,
            'direction' => ChargeDirection::class,
            'status' => ChargeStatus::class,
            'service_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<StayCharge>  $query
     */
    #[Scope]
    protected function confirmados(Builder $query): void
    {
        $query->where('status', ChargeStatus::Confirmed);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * The stay the charge comes from, whatever folio it is billed to.
     *
     * @return BelongsTo<Stay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The sale that took the product out of stock.
     *
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
