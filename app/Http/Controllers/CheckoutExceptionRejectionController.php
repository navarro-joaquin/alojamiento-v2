<?php

namespace App\Http\Controllers;

use App\Models\CheckoutException;
use App\Services\CheckoutExceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Rejects a pending request or revokes an approval not used yet.
 */
class CheckoutExceptionRejectionController extends Controller
{
    public function store(Request $request, CheckoutException $checkoutException, CheckoutExceptionService $checkoutExceptionService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $checkoutException->stay->branch_id);
        Gate::authorize('rechazar', $checkoutException);

        $validated = $request->validate([
            'rejection_notes' => ['required', 'string', 'max:500'],
        ], attributes: ['rejection_notes' => 'motivo del rechazo']);

        $resolvedException = $checkoutExceptionService->rechazar($checkoutException, $request->user(), $validated['rejection_notes']);

        return back()->with('status', 'Salida con deuda '.mb_strtolower($resolvedException->status->etiqueta()).'.');
    }
}
