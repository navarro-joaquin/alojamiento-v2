<?php

namespace App\Models;

use BcMath\Number;
use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock of a product in a warehouse. Only InventoryService changes it, in the
 * same transaction as the movement that explains the change.
 */
#[Fillable(['warehouse_id', 'product_id', 'quantity', 'average_cost'])]
class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
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
            'average_cost' => 'decimal:4',
        ];
    }

    /**
     * Value of the stock at its average cost, in Bs.
     */
    public function valorizado(): string
    {
        return (string) (new Number($this->quantity) * new Number($this->average_cost))->round(2);
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
}
