<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashSessionClosingController extends Controller
{
    public function store(Request $request, CashSession $cashSession, CashService $cashService): RedirectResponse
    {
        Gate::authorize('cerrar', $cashSession);

        $validated = $request->validate([
            'declared_amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['declared_amount' => 'efectivo contado']);

        $closedSession = $cashService->cerrarSesion($cashSession, $request->user(), (string) $validated['declared_amount'], $validated['closing_notes'] ?? null);

        return redirect()->route('cash-sessions.show', $closedSession)
            ->with('status', "Caja cerrada: {$closedSession->status->etiqueta()} (diferencia Bs {$closedSession->difference_amount}).");
    }
}
