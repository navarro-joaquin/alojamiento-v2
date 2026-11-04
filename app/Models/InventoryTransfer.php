<?php

namespace App\Models;

use App\Enums\InventoryTransferStatus;
use Database\Factories\InventoryTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock sent from one warehouse to another, possibly of another branch. Only
 * InventoryTransferService changes its status.
 */
#[Fillable([
    'from_warehouse_id', 'to_warehouse_id', 'status', 'notes', 'reception_notes',
    'created_by', 'sent_by', 'received_by', 'cancelled_by', 'sent_at', 'received_at', 'cancelled_at',
])]
class InventoryTransfer extends Model
{
    /** @use HasFactory<InventoryTransferFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryTransferStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Whether the transfer leaves from or arrives at a warehouse of the branch.
     */
    public function involucraSucursal(int $branchId): bool
    {
        return $this->fromWarehouse->branch_id === $branchId || $this->toWarehouse->branch_id === $branchId;
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * @return HasMany<InventoryTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
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
    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
