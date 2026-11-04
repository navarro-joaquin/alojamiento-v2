<?php

namespace App\Http\Controllers;

use App\Enums\CashDenomination;
use App\Models\CashSession;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * When bills and coins are detailed, their sum is the cash counted.
 */
class CashSessionClosingController extends Controller
{
    public function store(Request $request, CashSession $cashSession, CashService $cashService): RedirectResponse
    {
        Gate::authorize('cerrar', $cashSession);

        $denominations = CashDenomination::filtrarConteo((array) $request->input('denominations', []));

        $validated = $request->validate([
            'declared_amount' => [Rule::requiredIf($denominations === []), 'nullable', 'decimal:0,2', 'min:0', 'max:999999'],
            'denominations' => ['nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'declared_amount' => 'efectivo contado',
            'denominations.*' => 'cantidad',
        ]);

        $closedSession = $cashService->cerrarSesion(
            $cashSession,
            $request->user(),
            $denominations !== [] ? (string) CashDenomination::sumar($denominations) : (string) $validated['declared_amount'],
            $validated['closing_notes'] ?? null,
            $denominations,
        );

        return redirect()->route('cash-sessions.show', $closedSession)
            ->with('status', "Caja cerrada: {$closedSession->status->etiqueta()} (diferencia Bs {$closedSession->difference_amount}).");
    }
}
