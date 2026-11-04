<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\CashSession;
use App\Models\Stay;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Every payment form carries the operation_uuid generated when it was
 * rendered, so a double submission returns the payment already recorded.
 */
class PaymentController extends Controller
{
    public function store(Request $request, Stay $stay, CashService $cashService): RedirectResponse
    {
        Gate::authorize('cobrarPago', $stay);

        $validated = $request->validate([
            'cash_session_id' => ['required', 'integer', Rule::exists(CashSession::class, 'id')],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'operation_uuid' => ['required', 'uuid'],
        ]);

        $payment = $cashService->cobrarPago(
            $stay->folio,
            CashSession::findOrFail($validated['cash_session_id']),
            $request->user(),
            PaymentMethod::from($validated['payment_method']),
            PaymentType::from($validated['payment_type']),
            (string) $validated['amount'],
            $validated['operation_uuid'],
            $validated['reference'] ?? null,
        );

        return redirect()->route('stays.show', $stay)->with('status', "Cobro de Bs {$payment->amount} registrado.");
    }
}
