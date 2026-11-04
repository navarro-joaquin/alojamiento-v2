<?php

namespace App\Models;

use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of a stock change. Quantities are always positive; the
 * direction gives the sign.
 */
#[Fillable([
    'warehouse_id', 'product_id', 'performed_by', 'movement_type', 'direction', 'quantity', 'unit_cost',
    'balance_after', 'inventory_transfer_id', 'reference', 'notes', 'operation_uuid', 'request_hash',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'direction' => InventoryMovementDirection::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<InventoryTransfer, $this>
     */
    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
