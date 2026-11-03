<?php

namespace App\Models;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Enums\StayStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'room_type_id',
    'room_number',
    'floor',
    'capacity',
    'housekeeping_status',
    'operational_status',
])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'housekeeping_status' => HousekeepingStatus::class,
            'operational_status' => OperationalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return HasMany<Stay, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }

    /**
     * The stay currently occupying the room, if any.
     *
     * @return HasOne<Stay, $this>
     */
    public function activeStay(): HasOne
    {
        return $this->hasOne(Stay::class)->where('status', StayStatus::Active);
    }

    /**
     * @return HasMany<RoomStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(RoomStatusLog::class);
    }

    /**
     * Whether the room's own state allows a new check-in. Occupancy is checked
     * separately through the active stay.
     */
    public function estaDisponibleParaAsignar(): bool
    {
        return $this->operational_status === OperationalStatus::InService
            && $this->housekeeping_status->permiteAsignacion();
    }
}
