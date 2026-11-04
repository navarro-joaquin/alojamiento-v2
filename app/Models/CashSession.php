<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cash_register_id',
    'opened_by',
    'closed_by',
    'reviewed_by',
    'business_date',
    'opened_at',
    'opening_amount',
    'closed_at',
    'reviewed_at',
    'expected_amount',
    'declared_amount',
    'difference_amount',
    'closing_notes',
    'review_notes',
    'status',
])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'declared_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'status' => CashSessionStatus::class,
        ];
    }

    /**
     * @param  Builder<CashSession>  $query
     */
    #[Scope]
    protected function abiertas(Builder $query): void
    {
        $query->where('status', CashSessionStatus::Open);
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * @return HasMany<CashCount, $this>
     */
    public function cashCounts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }
}
