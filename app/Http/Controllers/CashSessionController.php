<?php

namespace App\Http\Controllers;

use App\Enums\CashMovementDirection;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\CashService;
use BcMath\Number;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function store(Request $request, CashRegister $cashRegister, CashService $cashService): RedirectResponse
    {
        Gate::authorize('abrirSesion', $cashRegister);

        $validated = $request->validate([
            'opening_amount' => ['required', 'decimal:0,2', 'min:0', 'max:999999'],
        ], attributes: ['opening_amount' => 'fondo inicial']);

        $cashSession = $cashService->abrirSesion($cashRegister, $request->user(), (string) $validated['opening_amount']);

        return redirect()->route('cash-sessions.show', $cashSession)->with('status', "Caja {$cashRegister->name} abierta.");
    }

    public function show(CashSession $cashSession, CashService $cashService): View
    {
        Gate::authorize('ver', $cashSession);

        $cashSession->load([
            'cashRegister',
            'openedByUser',
            'closedByUser',
            'movements' => fn ($query) => $query->with('performedByUser')->orderBy('occurred_at')->orderBy('id'),
        ]);

        return view('cash-sessions.show', [
            'cashSession' => $cashSession,
            'expectedCash' => $cashService->calcularEfectivoEsperado($cashSession),
            'netByPaymentMethod' => $this->sumarPorMetodo($cashSession->movements),
        ]);
    }

    /**
     * Net amount (in minus out) per payment method. Only cash counts towards
     * the expected cash; the other methods are shown for reconciliation.
     *
     * @param  Collection<int, CashMovement>  $movements
     * @return Collection<string, Number>
     */
    private function sumarPorMetodo(Collection $movements): Collection
    {
        return $movements
            ->groupBy(fn (CashMovement $movement): string => $movement->payment_method->etiqueta())
            ->map(fn (Collection $methodMovements): Number => $methodMovements->reduce(
                fn (Number $total, CashMovement $movement): Number => $movement->direction === CashMovementDirection::In
                    ? $total + new Number((string) $movement->amount)
                    : $total - new Number((string) $movement->amount),
                new Number('0.00'),
            )->round(2));
    }
}
