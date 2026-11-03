<?php

namespace App\Models;

use App\Enums\CheckoutExceptionStatus;
use Database\Factories\CheckoutExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authorization to check out a stay that still owes `outstanding_amount`.
 * It is approved by someone other than the requester, expires, and is
 * consumed by a single check-out.
 */
#[Fillable([
    'stay_id',
    'requested_by',
    'approved_by',
    'resolved_by',
    'outstanding_amount',
    'reason',
    'resolution_notes',
    'status',
    'requested_at',
    'approved_at',
    'expires_at',
    'used_at',
    'resolved_at',
])]
class CheckoutException extends Model
{
    /** @use HasFactory<CheckoutExceptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outstanding_amount' => 'decimal:2',
            'status' => CheckoutExceptionStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Requested or approved: at most one per stay.
     *
     * @param  Builder<CheckoutException>  $query
     */
    #[Scope]
    protected function abiertas(Builder $query): void
    {
        $query->whereIn('status', [CheckoutExceptionStatus::Requested, CheckoutExceptionStatus::Approved]);
    }

    /**
     * Approved and not yet expired. An approval past its expiry keeps the
     * approved status until a new request replaces it.
     */
    public function estaVigente(): bool
    {
        return $this->status === CheckoutExceptionStatus::Approved && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Stay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Who rejected or revoked the exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
