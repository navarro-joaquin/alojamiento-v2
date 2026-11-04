<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Accepts the difference of a session left pending review at closing.
 */
class CashSessionReviewController extends Controller
{
    public function store(Request $request, CashSession $cashSession, CashService $cashService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $cashSession->cashRegister->branch_id);
        Gate::authorize('revisar', $cashSession);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ], attributes: ['review_notes' => 'observaciones de la revisión']);

        $cashService->revisarSesion($cashSession, $request->user(), $validated['review_notes']);

        return redirect()->route('cash-sessions.show', $cashSession)->with('status', 'Cierre revisado: la sesión queda cerrada.');
    }
}
