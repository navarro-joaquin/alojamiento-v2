<?php

namespace App\Http\Controllers;

use App\Models\Stay;
use App\Services\ChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The fields carry a discount_ prefix because the stay page also has the
 * charge form, and both would otherwise share old input and errors.
 */
class StayDiscountController extends Controller
{
    public function store(Request $request, Stay $stay, ChargeService $chargeService): RedirectResponse
    {
        Gate::authorize('registrarDescuento', $stay);

        $validated = $request->validate([
            'discount_description' => ['required', 'string', 'max:255'],
            'discount_amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999'],
        ], attributes: [
            'discount_description' => 'motivo del descuento',
            'discount_amount' => 'importe del descuento',
        ]);

        $chargeService->registrarDescuento($stay, $request->user(), $validated['discount_description'], (string) $validated['discount_amount']);

        return redirect()->route('stays.show', $stay)->with('status', 'Descuento aplicado.');
    }
}
