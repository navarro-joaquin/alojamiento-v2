<?php

namespace App\Http\Controllers;

use App\Enums\OperationalStatus;
use App\Models\Room;
use App\Services\HousekeepingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoomOperationalStatusController extends Controller
{
    /**
     * Taking a room out of service requires a reason; returning it does not.
     */
    public function update(Request $request, Room $room, HousekeepingService $housekeepingService): RedirectResponse
    {
        Gate::authorize('cambiarEstadoOperativo', $room);

        $validated = $request->validate([
            'operational_status' => ['required', Rule::enum(OperationalStatus::class)],
            'reason' => [Rule::requiredIf($request->input('operational_status') !== OperationalStatus::InService->value), 'nullable', 'string', 'max:255'],
        ], attributes: ['reason' => 'motivo']);

        $operationalStatus = OperationalStatus::from($validated['operational_status']);
        $housekeepingService->cambiarEstadoOperativo($room, $operationalStatus, $request->user(), $validated['reason'] ?? null);

        return back()->with('status', "Habitación {$room->room_number}: {$operationalStatus->etiqueta()}.");
    }
}
