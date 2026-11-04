<?php

namespace App\Http\Controllers;

use App\Enums\FolioType;
use App\Models\CashSession;
use App\Models\Folio;
use App\Services\CounterSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VoidedCounterSaleController extends Controller
{
    /**
     * Void a counter sale: stock back to the warehouse and money back to the client.
     */
    public function store(Request $request, Folio $counterSale, CounterSaleService $counterSaleService): RedirectResponse
    {
        abort_unless($counterSale->folio_type === FolioType::Counter, 404);
        $this->asegurarDeLaSucursalActiva($request, $counterSale->branch_id);
        Gate::authorize('anularVentaDeMostrador', $counterSale);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
            'void_cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
        ], attributes: [
            'void_reason' => 'motivo',
            'void_cash_session_id' => 'caja',
        ]);

        $counterSaleService->anular(
            $counterSale,
            $request->user(),
            $validated['void_reason'],
            isset($validated['void_cash_session_id']) ? CashSession::findOrFail($validated['void_cash_session_id']) : null,
        );

        return redirect()->route('counter-sales.show', $counterSale)->with('status', "Venta de mostrador #{$counterSale->id} anulada.");
    }
}
