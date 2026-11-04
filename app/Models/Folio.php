<?php

namespace App\Models;

use App\Enums\FolioType;
use Database\Factories\FolioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The account charges are billed to and payments are applied to. A stay
 * folio belongs to a single stay; other folios have no stay.
 */
#[Fillable([
    'branch_id',
    'folio_type',
    'stay_id',
    'holder_name',
    'created_by',
    'operation_uuid',
    'request_hash',
    'voided_by',
    'voided_at',
    'void_reason',
])]
class Folio extends Model
{
    /** @use HasFactory<FolioFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folio_type' => FolioType::class,
            'voided_at' => 'datetime',
        ];
    }

    public function estaAnulada(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * The page where the folio is operated: its stay or its counter sale.
     */
    public function urlDeDetalle(): string
    {
        return $this->folio_type === FolioType::Stay
            ? route('stays.show', $this->stay_id)
            : route('counter-sales.show', $this);
    }

    /**
     * Who the folio is billed to, for receipts and listings.
     */
    public function descripcion(): string
    {
        return match ($this->folio_type) {
            FolioType::Stay => "Habitación {$this->stay->room->room_number}",
            FolioType::Counter => "Venta de mostrador #{$this->id}",
        };
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<StayCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(StayCharge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
