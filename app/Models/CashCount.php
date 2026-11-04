<?php

namespace App\Models;

use App\Enums\CashCountType;
use Database\Factories\CashCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash physically counted in a session: during it (for example at a shift
 * change) or when closing it.
 */
#[Fillable([
    'cash_session_id',
    'counted_by',
    'count_type',
    'expected_amount',
    'declared_amount',
    'difference_amount',
    'denominations',
    'notes',
    'counted_at',
])]
class CashCount extends Model
{
    /** @use HasFactory<CashCountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count_type' => CashCountType::class,
            'expected_amount' => 'decimal:2',
            'declared_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'denominations' => 'array',
            'counted_at' => 'datetime',
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
    public function countedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
