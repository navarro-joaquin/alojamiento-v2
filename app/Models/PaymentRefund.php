<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use Database\Factories\PaymentRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'payment_id',
    'cash_session_id',
    'processed_by',
    'amount',
    'refund_method',
    'status',
    'reference',
    'reason',
    'operation_uuid',
    'request_hash',
    'refunded_at',
])]
class PaymentRefund extends Model
{
    /** @use HasFactory<PaymentRefundFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refund_method' => PaymentMethod::class,
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PaymentRefund>  $query
     */
    #[Scope]
    protected function confirmadas(Builder $query): void
    {
        $query->where('status', RefundStatus::Confirmed);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }
}
