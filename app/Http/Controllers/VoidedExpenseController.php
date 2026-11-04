<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Voids a confirmed expense; the cash of a cash expense comes back into the
 * chosen open register.
 */
class VoidedExpenseController extends Controller
{
    public function store(Request $request, Expense $expense, ExpenseService $expenseService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $expense->branch_id);
        Gate::authorize('anular', $expense);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:500'],
            'void_cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
        ], attributes: [
            'void_reason' => 'motivo de la anulación',
            'void_cash_session_id' => 'caja',
        ]);

        $expenseService->anular(
            $expense,
            $request->user(),
            $validated['void_reason'],
            isset($validated['void_cash_session_id']) ? CashSession::findOrFail($validated['void_cash_session_id']) : null,
        );

        return redirect()->route('expenses.index')->with('status', "Gasto de Bs {$expense->amount} anulado.");
    }
}
