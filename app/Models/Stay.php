<?php

namespace App\Models;

use App\Enums\StayStatus;
use Database\Factories\StayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'room_id',
    'room_rate_id',
    'created_by',
    'check_in_at',
    'expected_check_out_at',
    'check_out_at',
    'status',
    'rate_amount',
    'adults',
    'children',
    'notes',
])]
class Stay extends Model
{
    /** @use HasFactory<StayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'expected_check_out_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => StayStatus::class,
            'rate_amount' => 'decimal:2',
            'adults' => 'integer',
            'children' => 'integer',
        ];
    }

    /**
     * @param  Builder<Stay>  $query
     */
    #[Scope]
    protected function activas(Builder $query): void
    {
        $query->where('status', StayStatus::Active);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<RoomRate, $this>
     */
    public function roomRate(): BelongsTo
    {
        return $this->belongsTo(RoomRate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Guest, $this>
     */
    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'stay_guests')
            ->withPivot(['is_primary', 'relationship'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<StayRoomAssignment, $this>
     */
    public function roomAssignments(): HasMany
    {
        return $this->hasMany(StayRoomAssignment::class);
    }

    /**
     * The stay's own account. Its balance is what the guest owes.
     *
     * @return HasOne<Folio, $this>
     */
    public function folio(): HasOne
    {
        return $this->hasOne(Folio::class);
    }

    /**
     * Charges that come from this stay, whatever folio they are billed to.
     *
     * @return HasMany<StayCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(StayCharge::class);
    }

    /**
     * Payments applied to the stay's own folio.
     *
     * @return HasManyThrough<Payment, Folio, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Folio::class);
    }

    /**
     * @return HasMany<CheckoutException, $this>
     */
    public function checkoutExceptions(): HasMany
    {
        return $this->hasMany(CheckoutException::class);
    }
}
