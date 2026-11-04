<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RetiredFixedAssetController extends Controller
{
    public function store(Request $request, FixedAsset $fixedAsset, FixedAssetService $fixedAssetService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $fixedAsset->branch_id);
        Gate::authorize('darDeBaja', $fixedAsset);

        $validated = $request->validate([
            'retirement_reason' => ['required', 'string', 'max:500'],
        ], attributes: [
            'retirement_reason' => 'motivo de la baja',
        ]);

        $fixedAssetService->darDeBaja($fixedAsset, $request->user(), $validated['retirement_reason']);

        return redirect()->route('fixed-assets.show', $fixedAsset)->with('status', "Activo {$fixedAsset->asset_code} dado de baja.");
    }
}
