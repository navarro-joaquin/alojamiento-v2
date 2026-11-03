<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Models\Room;
use App\Services\HousekeepingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoomHousekeepingStatusController extends Controller
{
    public function update(Request $request, Room $room, HousekeepingService $housekeepingService): RedirectResponse
    {
        Gate::authorize('cambiarEstadoLimpieza', $room);

        $validated = $request->validate([
            'housekeeping_status' => ['required', Rule::enum(HousekeepingStatus::class)],
        ]);

        $housekeepingStatus = HousekeepingStatus::from($validated['housekeeping_status']);
        $housekeepingService->cambiarEstadoLimpieza($room, $housekeepingStatus, $request->user());

        return back()->with('status', "Habitación {$room->room_number}: {$housekeepingStatus->etiqueta()}.");
    }
}
