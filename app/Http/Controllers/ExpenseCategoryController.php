<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', ExpenseCategory::class);

        return view('expense-categories.index', [
            'expenseCategories' => ExpenseCategory::query()->withCount('expenses')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', ExpenseCategory::class);

        return view('expense-categories.create', ['expenseCategory' => new ExpenseCategory(['is_active' => true])]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        $expenseCategory = ExpenseCategory::create($request->validated());

        return redirect()->route('expense-categories.index')->with('status', "Categoría {$expenseCategory->name} creada.");
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        Gate::authorize('gestionar', ExpenseCategory::class);

        return view('expense-categories.edit', ['expenseCategory' => $expenseCategory]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        return redirect()->route('expense-categories.index')->with('status', "Categoría {$expenseCategory->name} actualizada.");
    }
}
