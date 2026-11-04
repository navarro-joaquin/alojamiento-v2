<?php

namespace App\Http\Controllers;

use App\Enums\FixedAssetStatus;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Room;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FixedAssetMovementController extends Controller
{
    /**
     * Move the asset to a room or another branch, or change its status. An
     * asset handed over to another branch leaves the list of this one.
     */
    public function store(Request $request, FixedAsset $fixedAsset, FixedAssetService $fixedAssetService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $fixedAsset->branch_id);
        Gate::authorize('gestionar', [FixedAsset::class, $this->sucursalActiva($request)]);

        $validated = $request->validate([
            'to_branch_id' => ['required', 'integer', Rule::exists(Branch::class, 'id')],
            'to_status' => ['required', Rule::enum(FixedAssetStatus::class)->only(FixedAssetStatus::operativos())],
            'to_room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')],
            'movement_notes' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'to_branch_id' => 'sucursal',
            'to_status' => 'estado',
            'to_room_id' => 'habitación',
            'movement_notes' => 'observaciones',
        ]);

        $fixedAssetService->mover(
            $fixedAsset,
            $request->user(),
            Branch::findOrFail($validated['to_branch_id']),
            FixedAssetStatus::from($validated['to_status']),
            isset($validated['to_room_id']) ? Room::findOrFail($validated['to_room_id']) : null,
            $validated['movement_notes'] ?? null,
        );

        $fixedAsset->refresh();

        return $fixedAsset->branch_id === $this->sucursalActiva($request)->id
            ? redirect()->route('fixed-assets.show', $fixedAsset)->with('status', 'Movimiento registrado.')
            : redirect()->route('fixed-assets.index')->with('status', "Activo {$fixedAsset->asset_code} entregado a otra sucursal.");
    }
}
