<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', RoomType::class);

        return view('room-types.index', [
            'roomTypes' => RoomType::query()->withCount('rooms')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', RoomType::class);

        return view('room-types.create', ['roomType' => new RoomType(['default_capacity' => 2])]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        $roomType = RoomType::create($request->validated());

        return redirect()->route('room-types.index')->with('status', "Tipo {$roomType->name} creado.");
    }

    public function edit(RoomType $roomType): View
    {
        Gate::authorize('gestionar', RoomType::class);

        return view('room-types.edit', ['roomType' => $roomType]);
    }

    public function update(RoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($request->validated());

        return redirect()->route('room-types.index')->with('status', "Tipo {$roomType->name} actualizado.");
    }
}
