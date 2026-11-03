<?php

namespace App\Models;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'cash_session_id',
    'performed_by',
    'movement_type',
    'payment_method',
    'direction',
    'amount',
    'source_type',
    'source_id',
    'affects_cash',
    'operation_uuid',
    'description',
    'occurred_at',
])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => CashMovementType::class,
            'payment_method' => PaymentMethod::class,
            'direction' => CashMovementDirection::class,
            'amount' => 'decimal:2',
            'affects_cash' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * The payment, refund or other operation that produced the movement.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
