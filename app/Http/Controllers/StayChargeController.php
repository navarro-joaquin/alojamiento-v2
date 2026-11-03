<?php

namespace App\Http\Controllers;

use App\Enums\ChargeType;
use App\Models\Stay;
use App\Services\ChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StayChargeController extends Controller
{
    public function store(Request $request, Stay $stay, ChargeService $chargeService): RedirectResponse
    {
        Gate::authorize('registrarCargo', $stay);

        $validated = $request->validate([
            'charge_type' => ['required', Rule::enum(ChargeType::class)->only(ChargeService::MANUAL_DEBIT_TYPES)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:9999'],
            'unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
        ]);

        $chargeService->registrarCargo(
            $stay,
            $request->user(),
            ChargeType::from($validated['charge_type']),
            $validated['description'],
            (string) $validated['quantity'],
            (string) $validated['unit_price'],
        );

        return redirect()->route('stays.show', $stay)->with('status', 'Cargo registrado.');
    }
}
