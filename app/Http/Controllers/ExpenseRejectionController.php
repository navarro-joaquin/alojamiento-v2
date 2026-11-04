<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseRejectionController extends Controller
{
    public function store(Request $request, Expense $expense, ExpenseService $expenseService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $expense->branch_id);
        Gate::authorize('rechazar', $expense);

        $validated = $request->validate([
            'rejection_notes' => ['required', 'string', 'max:500'],
        ], attributes: ['rejection_notes' => 'motivo del rechazo']);

        $expenseService->rechazar($expense, $request->user(), $validated['rejection_notes']);

        return redirect()->route('expenses.index')->with('status', 'Gasto rechazado.');
    }
}
