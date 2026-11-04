<?php

namespace App\Http\Controllers;

use App\Enums\FixedAssetStatus;
use App\Http\Requests\FixedAssetRequest;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Room;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verActivos', [FixedAsset::class, $branch]);

        $fixedAssets = $branch->fixedAssets()->with('room')->orderBy('asset_code')->get();

        return view('fixed-assets.index', [
            'fixedAssets' => $fixedAssets->where('status', '!=', FixedAssetStatus::Retired),
            'retiredFixedAssets' => $fixedAssets->where('status', FixedAssetStatus::Retired),
        ]);
    }

    public function create(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('gestionar', [FixedAsset::class, $branch]);

        return view('fixed-assets.create', [
            'fixedAsset' => new FixedAsset(['status' => FixedAssetStatus::InStorage]),
            'roomOptions' => $this->opcionesDeHabitacion($branch),
            'statuses' => FixedAssetStatus::operativos(),
        ]);
    }

    public function store(FixedAssetRequest $request, FixedAssetService $fixedAssetService): RedirectResponse
    {
        $fixedAsset = $fixedAssetService->registrar(
            $this->sucursalActiva($request),
            $request->user(),
            $request->datosDelActivo(),
            FixedAssetStatus::from($request->validated('status')),
            $request->validated('room_id') ? Room::findOrFail($request->validated('room_id')) : null,
        );

        return redirect()->route('fixed-assets.show', $fixedAsset)->with('status', "Activo {$fixedAsset->asset_code} registrado.");
    }

    /**
     * The asset with its history and the forms to move or retire it.
     */
    public function show(Request $request, FixedAsset $fixedAsset): View
    {
        $branch = $this->sucursalActiva($request);
        $this->asegurarDeLaSucursalActiva($request, $fixedAsset->branch_id);
        Gate::authorize('verActivos', [FixedAsset::class, $branch]);

        $fixedAsset->load(['room', 'movements' => fn ($query) => $query
            ->with(['fromBranch', 'toBranch', 'fromRoom', 'toRoom', 'performedByUser'])
            ->latest('moved_at')
            ->latest('id')]);

        return view('fixed-assets.show', [
            'fixedAsset' => $fixedAsset,
            'branchOptions' => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'roomOptions' => $this->opcionesDeHabitacion($branch),
            'statuses' => FixedAssetStatus::operativos(),
        ]);
    }

    public function edit(Request $request, FixedAsset $fixedAsset): View
    {
        $this->asegurarDeLaSucursalActiva($request, $fixedAsset->branch_id);
        Gate::authorize('gestionar', [FixedAsset::class, $this->sucursalActiva($request)]);

        return view('fixed-assets.edit', ['fixedAsset' => $fixedAsset]);
    }

    /**
     * Only descriptive data: location and status change through movements.
     */
    public function update(FixedAssetRequest $request, FixedAsset $fixedAsset): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $fixedAsset->branch_id);

        $fixedAsset->update($request->datosDelActivo());

        return redirect()->route('fixed-assets.show', $fixedAsset)->with('status', "Activo {$fixedAsset->asset_code} actualizado.");
    }

    /**
     * @return array<int|string, string>
     */
    private function opcionesDeHabitacion(Branch $branch): array
    {
        return ['' => 'Sin habitación'] + $branch->rooms()->orderBy('room_number')->pluck('room_number', 'id')
            ->map(fn (string $roomNumber): string => "Habitación {$roomNumber}")
            ->all();
    }
}
