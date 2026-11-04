<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * Pending expenses first, then the latest ones of the active branch.
     */
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verGastos', [Expense::class, $branch]);

        $expenses = Expense::query()
            ->whereBelongsTo($branch)
            ->with(['expenseCategory', 'createdByUser', 'approvedByUser', 'resolvedByUser', 'cashSession.cashRegister'])
            ->latest('id');

        return view('expenses.index', [
            'pendingExpenses' => (clone $expenses)->where('status', ExpenseStatus::PendingApproval)->get(),
            'recentExpenses' => (clone $expenses)->where('status', '!=', ExpenseStatus::PendingApproval)->limit(50)->get(),
            'expenseCategories' => ExpenseCategory::query()->activas()->orderBy('name')->get(),
            'openCashSessions' => CashSession::query()
                ->abiertas()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($branch))
                ->with('cashRegister')
                ->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'approvalThreshold' => $branch->expense_approval_threshold ?? config('lodging.expense_approval_threshold'),
        ]);
    }

    /**
     * The form carries the operation_uuid generated when it was rendered, so a
     * double submission returns the expense already recorded.
     */
    public function store(Request $request, ExpenseService $expenseService): RedirectResponse
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('registrar', [Expense::class, $branch]);

        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer', Rule::exists(ExpenseCategory::class, 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'expense_category_id' => 'categoría',
            'cash_session_id' => 'caja',
        ]);

        $expense = $expenseService->registrar(
            $branch,
            $request->user(),
            ExpenseCategory::findOrFail($validated['expense_category_id']),
            $validated['description'],
            (string) $validated['amount'],
            PaymentMethod::from($validated['payment_method']),
            $validated['operation_uuid'],
            isset($validated['cash_session_id']) ? CashSession::findOrFail($validated['cash_session_id']) : null,
            $validated['reference'] ?? null,
        );

        return redirect()->route('expenses.index')->with('status', $expense->status === ExpenseStatus::PendingApproval
            ? "Gasto de Bs {$expense->amount} registrado: supera el umbral de la sucursal y queda pendiente de aprobación."
            : "Gasto de Bs {$expense->amount} registrado.");
    }
}
