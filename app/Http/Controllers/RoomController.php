<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRequest;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('gestionar', [Room::class, $branch]);

        return view('rooms.index', [
            'rooms' => Room::query()->whereBelongsTo($branch)->with('roomType')->orderBy('floor')->orderBy('room_number')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar', [Room::class, $this->sucursalActiva($request)]);

        return view('rooms.create', ['room' => new Room, 'roomTypes' => RoomType::query()->orderBy('name')->get()]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = $this->sucursalActiva($request)->rooms()->create($request->validated());

        return redirect()->route('rooms.index')->with('status', "Habitación {$room->room_number} creada.");
    }

    public function edit(Request $request, Room $room): View
    {
        $this->asegurarDeLaSucursalActiva($request, $room->branch_id);
        Gate::authorize('gestionar', [Room::class, $this->sucursalActiva($request)]);

        return view('rooms.edit', ['room' => $room, 'roomTypes' => RoomType::query()->orderBy('name')->get()]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $room->branch_id);

        $room->update($request->validated());

        return redirect()->route('rooms.index')->with('status', "Habitación {$room->room_number} actualizada.");
    }
}
