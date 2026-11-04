<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal receipt of a payment, numbered per branch. The fiscal invoice is
 * issued in an external system. A receipt is never edited: it is voided with
 * a reason and, if needed, replaced by a new one with a new number.
 */
#[Fillable([
    'branch_id',
    'folio_id',
    'payment_id',
    'prefix',
    'number',
    'holder_name',
    'holder_document',
    'amount',
    'status',
    'issued_by',
    'issued_at',
    'voided_by',
    'voided_at',
    'void_reason',
])]
#[Hidden(['holder_document'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'holder_document' => 'encrypted',
            'amount' => 'decimal:2',
            'status' => ReceiptStatus::class,
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * The number as printed, for example "CEN-000042".
     */
    public function numeroCompleto(): string
    {
        return $this->prefix.'-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
