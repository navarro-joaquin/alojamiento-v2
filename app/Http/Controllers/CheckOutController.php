<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Stay;
use App\Services\ReceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CheckOutController extends Controller
{
    /**
     * The cash session is only needed to refund a credit balance in cash.
     */
    public function store(Request $request, Stay $stay, ReceptionService $receptionService): RedirectResponse
    {
        Gate::authorize('registrarSalida', $stay);

        $validated = $request->validate([
            'cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
        ]);

        $completedStay = $receptionService->registrarSalida(
            $stay,
            $request->user(),
            isset($validated['cash_session_id']) ? CashSession::findOrFail($validated['cash_session_id']) : null,
        );

        return redirect()->route('occupancy.index')->with('status', "Salida registrada de la habitación {$completedStay->room->room_number}.");
    }
}
