<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Room;
use App\Models\RoomRate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class RateService
{
    /**
     * Resolve the nightly rate for a room on a date.
     *
     * Among the active rates of the room's branch and type that are valid on the
     * date and match the occupancy and weekday, the highest priority wins; ties
     * go to the most specific rate (fixed occupancy, then fixed weekdays), then
     * to the most recent one.
     *
     * @throws BusinessRuleException
     */
    public function resolverTarifa(Room $room, int $occupancy, CarbonInterface $date): RoomRate
    {
        $rate = RoomRate::query()
            ->where('branch_id', $room->branch_id)
            ->where('room_type_id', $room->room_type_id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_to')
                ->orWhereDate('valid_to', '>=', $date))
            ->where(fn (Builder $query) => $query
                ->whereNull('occupancy')
                ->orWhere('occupancy', $occupancy))
            ->where(fn (Builder $query) => $query
                ->whereNull('days_of_week')
                ->orWhereJsonContains('days_of_week', $date->dayOfWeekIso))
            ->orderByDesc('priority')
            ->orderByRaw('occupancy IS NULL')
            ->orderByRaw('days_of_week IS NULL')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($rate === null) {
            throw BusinessRuleException::sinTarifaAplicable();
        }

        return $rate;
    }

    /**
     * Reject a rate that would compete with another one on equal terms: same
     * branch, room type, priority and occupancy, with overlapping validity and
     * weekdays. Such a pair would make the resolved rate depend on insertion order.
     *
     * @throws BusinessRuleException
     */
    public function verificarSinSolapamiento(RoomRate $rate): void
    {
        $candidates = RoomRate::query()
            ->where('branch_id', $rate->branch_id)
            ->where('room_type_id', $rate->room_type_id)
            ->where('priority', $rate->priority)
            ->where('is_active', true)
            ->when($rate->exists, fn (Builder $query) => $query->whereKeyNot($rate->getKey()))
            ->when(
                $rate->occupancy === null,
                fn (Builder $query) => $query->whereNull('occupancy'),
                fn (Builder $query) => $query->where('occupancy', $rate->occupancy),
            )
            ->when($rate->valid_to !== null, fn (Builder $query) => $query->whereDate('valid_from', '<=', $rate->valid_to))
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_to')
                ->orWhereDate('valid_to', '>=', $rate->valid_from))
            ->get();

        $overlappingRate = $candidates->first(
            fn (RoomRate $candidate) => $this->compartenDias($rate->days_of_week, $candidate->days_of_week)
        );

        if ($overlappingRate !== null) {
            throw BusinessRuleException::tarifaSolapada();
        }
    }

    /**
     * Two "every day" rates share all days; a fixed-weekday rate is more specific
     * than an "every day" one, so that pair does not compete.
     *
     * @param  array<int, int>|null  $firstDays
     * @param  array<int, int>|null  $secondDays
     */
    private function compartenDias(?array $firstDays, ?array $secondDays): bool
    {
        if ($firstDays === null || $secondDays === null) {
            return $firstDays === null && $secondDays === null;
        }

        return array_intersect($firstDays, $secondDays) !== [];
    }
}
