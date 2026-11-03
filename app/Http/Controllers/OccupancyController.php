<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OccupancyController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::query()
            ->whereBelongsTo($this->sucursalActiva($request))
            ->with(['roomType', 'activeStay.guests' => fn ($query) => $query->wherePivot('is_primary', true)])
            ->orderBy('floor')
            ->orderBy('room_number')
            ->get();

        return view('occupancy.index', [
            'rooms' => $rooms,
            'summary' => [
                'Ocupadas' => $rooms->whereNotNull('activeStay')->count(),
                'Disponibles' => $rooms->filter(fn (Room $room): bool => $room->activeStay === null && $room->estaDisponibleParaAsignar())->count(),
                'Sucias' => $rooms->where('housekeeping_status', HousekeepingStatus::Dirty)->count(),
                'Fuera de servicio' => $rooms->where('operational_status', '!=', OperationalStatus::InService)->count(),
            ],
            'housekeepingStatuses' => HousekeepingStatus::cases(),
        ]);
    }
}
