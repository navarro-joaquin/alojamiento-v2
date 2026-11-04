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
 * Partial count of an open session. The fields carry a count_ prefix because
 * the session page also has the closing form. When bills and coins are
 * detailed, their sum is the cash counted.
 */
class CashCountController extends Controller
{
    public function store(Request $request, CashSession $cashSession, CashService $cashService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $cashSession->cashRegister->branch_id);
        Gate::authorize('registrarArqueo', $cashSession);

        $denominations = CashDenomination::filtrarConteo((array) $request->input('count_denominations', []));

        $validated = $request->validate([
            'count_declared_amount' => [Rule::requiredIf($denominations === []), 'nullable', 'decimal:0,2', 'min:0', 'max:999999'],
            'count_denominations' => ['nullable', 'array'],
            'count_denominations.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'count_notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'count_declared_amount' => 'efectivo contado',
            'count_denominations.*' => 'cantidad',
        ]);

        $cashCount = $cashService->registrarArqueo(
            $cashSession,
            $request->user(),
            $denominations !== [] ? (string) CashDenomination::sumar($denominations) : (string) $validated['count_declared_amount'],
            $denominations,
            $validated['count_notes'] ?? null,
        );

        return redirect()->route('cash-sessions.show', $cashSession)
            ->with('status', "Arqueo registrado: Bs {$cashCount->declared_amount} contados, diferencia Bs {$cashCount->difference_amount}.");
    }
}
