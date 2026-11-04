<?php

namespace App\Http\Controllers;

use App\Models\CheckoutException;
use App\Models\Stay;
use App\Services\CheckoutExceptionService;
use App\Services\FolioBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CheckoutExceptionController extends Controller
{
    /**
     * Open requests and approvals of the active branch, with the current debt
     * of each stay so the approver sees whether it still matches.
     */
    public function index(Request $request, FolioBalanceService $folioBalanceService): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verPendientes', [CheckoutException::class, $branch]);

        $checkoutExceptions = CheckoutException::query()
            ->abiertas()
            ->whereHas('stay', fn ($query) => $query->whereBelongsTo($branch))
            ->with(['stay.room', 'stay.guests', 'requestedByUser', 'approvedByUser'])
            ->orderBy('requested_at')
            ->get();

        return view('checkout-exceptions.index', [
            'checkoutExceptions' => $checkoutExceptions,
            'currentDebts' => $checkoutExceptions->mapWithKeys(fn (CheckoutException $checkoutException): array => [
                $checkoutException->id => $folioBalanceService->calcular($checkoutException->stay->folio),
            ]),
        ]);
    }

    /**
     * The field carries an exception_ prefix because the stay page has other
     * forms whose old input and errors would otherwise be shared.
     */
    public function store(Request $request, Stay $stay, CheckoutExceptionService $checkoutExceptionService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $stay->branch_id);
        Gate::authorize('solicitarSalidaConDeuda', $stay);

        $validated = $request->validate([
            'exception_reason' => ['required', 'string', 'max:500'],
        ], attributes: ['exception_reason' => 'motivo']);

        $checkoutException = $checkoutExceptionService->solicitar($stay, $request->user(), $validated['exception_reason']);

        return redirect()->route('stays.show', $stay)
            ->with('status', "Salida con deuda de Bs {$checkoutException->outstanding_amount} solicitada: falta que la autorice un supervisor.");
    }
}
