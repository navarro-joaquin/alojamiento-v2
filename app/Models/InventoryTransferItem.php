<?php

namespace App\Models;

use Database\Factories\InventoryTransferItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product of a transfer. The unit cost is the source average cost when it
 * was sent; the received quantity is set on reception.
 */
#[Fillable(['inventory_transfer_id', 'product_id', 'quantity', 'unit_cost', 'received_quantity'])]
class InventoryTransferItem extends Model
{
    /** @use HasFactory<InventoryTransferItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'received_quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<InventoryTransfer, $this>
     */
    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
