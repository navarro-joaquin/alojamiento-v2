<?php

namespace App\Http\Controllers;

use App\Enums\CashSessionStatus;
use App\Http\Requests\CashRegisterRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);

        Gate::authorize('verCajas', [CashRegister::class, $branch]);

        $cashRegisters = CashRegister::query()
            ->whereBelongsTo($branch)
            ->with(['sessions' => fn ($query) => $query->abiertas()->with('openedByUser')])
            ->orderBy('name')
            ->get();

        return view('cash-registers.index', [
            'cashRegisters' => $cashRegisters->where('is_active', true),
            'inactiveCashRegisters' => $cashRegisters->where('is_active', false),
            'sessionsPendingReview' => CashSession::query()
                ->where('status', CashSessionStatus::PendingReview)
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($branch))
                ->with(['cashRegister', 'closedByUser'])
                ->orderBy('closed_at')
                ->get(),
            'recentSessions' => CashSession::query()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($branch))
                ->whereNotNull('closed_at')
                ->with(['cashRegister', 'closedByUser'])
                ->latest('closed_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar', [CashRegister::class, $this->sucursalActiva($request)]);

        return view('cash-registers.create', ['cashRegister' => new CashRegister(['is_active' => true])]);
    }

    public function store(CashRegisterRequest $request): RedirectResponse
    {
        $cashRegister = $this->sucursalActiva($request)->cashRegisters()->create($request->validated());

        return redirect()->route('cash-registers.index')->with('status', "Caja {$cashRegister->name} creada.");
    }

    public function edit(Request $request, CashRegister $cashRegister): View
    {
        $this->asegurarDeLaSucursalActiva($request, $cashRegister->branch_id);
        Gate::authorize('gestionar', [CashRegister::class, $this->sucursalActiva($request)]);

        return view('cash-registers.edit', ['cashRegister' => $cashRegister]);
    }

    public function update(CashRegisterRequest $request, CashRegister $cashRegister): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $cashRegister->branch_id);

        $cashRegister->update($request->validated());

        return redirect()->route('cash-registers.index')->with('status', "Caja {$cashRegister->name} actualizada.");
    }
}
