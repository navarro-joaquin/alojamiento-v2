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
 * Approves a pending expense. A cash expense leaves the chosen open register
 * at this moment, not when it was registered.
 */
class ExpenseApprovalController extends Controller
{
    public function store(Request $request, Expense $expense, ExpenseService $expenseService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $expense->branch_id);
        Gate::authorize('aprobar', $expense);

        $validated = $request->validate([
            'approval_cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
        ], attributes: ['approval_cash_session_id' => 'caja']);

        $expenseService->aprobar(
            $expense,
            $request->user(),
            isset($validated['approval_cash_session_id']) ? CashSession::findOrFail($validated['approval_cash_session_id']) : null,
        );

        return redirect()->route('expenses.index')->with('status', "Gasto de Bs {$expense->amount} aprobado.");
    }
}
