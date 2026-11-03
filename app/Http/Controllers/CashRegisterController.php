<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);

        Gate::authorize('verCajas', [CashRegister::class, $branch]);

        return view('cash-registers.index', [
            'cashRegisters' => CashRegister::query()
                ->whereBelongsTo($branch)
                ->where('is_active', true)
                ->with(['sessions' => fn ($query) => $query->abiertas()->with('openedByUser')])
                ->orderBy('name')
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
}
