<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Stay;
use App\Services\ReceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomChangeController extends Controller
{
    public function create(Stay $stay): View
    {
        Gate::authorize('cambiarHabitacion', $stay);

        return view('room-changes.create', [
            'stay' => $stay->load('room'),
            'availableRooms' => Room::query()
                ->whereBelongsTo($stay->branch)
                ->whereDoesntHave('activeStay')
                ->with('roomType')
                ->orderBy('room_number')
                ->get()
                ->filter(fn (Room $room): bool => $room->estaDisponibleParaAsignar()),
        ]);
    }

    public function store(Request $request, Stay $stay, ReceptionService $receptionService): RedirectResponse
    {
        Gate::authorize('cambiarHabitacion', $stay);

        $validated = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists(Room::class, 'id')],
            'reason' => ['required', 'string', 'max:255'],
            'agreed_rate_amount' => ['nullable', 'decimal:0,2', 'gt:0', 'max:999999'],
        ]);

        $movedStay = $receptionService->cambiarHabitacion(
            $stay,
            Room::findOrFail($validated['room_id']),
            $request->user(),
            $validated['reason'],
            isset($validated['agreed_rate_amount']) ? (string) $validated['agreed_rate_amount'] : null,
        );

        return redirect()->route('stays.show', $movedStay)->with('status', "Estadía trasladada a la habitación {$movedStay->room->room_number}.");
    }
}
