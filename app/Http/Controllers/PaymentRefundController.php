<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Stay;
use App\Services\CashService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Returns the stay's credit through one of its payments. The fields carry a
 * refund_ prefix because the stay page also has the payment form; like it,
 * the form carries the operation_uuid generated when it was rendered.
 */
class PaymentRefundController extends Controller
{
    public function store(Request $request, Stay $stay, CashService $cashService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $stay->branch_id);

        $validated = $request->validate([
            'refund_payment_id' => ['required', 'integer', Rule::exists(Payment::class, 'id')->where('folio_id', $stay->folio->id)],
            'refund_cash_session_id' => ['nullable', 'integer', Rule::exists(CashSession::class, 'id')],
            'refund_amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999'],
            'refund_reason' => ['required', 'string', 'max:255'],
            'refund_operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'refund_payment_id' => 'pago',
            'refund_cash_session_id' => 'caja',
            'refund_amount' => 'importe de la devolución',
            'refund_reason' => 'motivo de la devolución',
        ]);

        $payment = Payment::findOrFail($validated['refund_payment_id']);
        Gate::authorize('devolver', $payment);

        $refund = $cashService->devolverPago(
            $payment,
            $request->user(),
            (string) $validated['refund_amount'],
            $validated['refund_reason'],
            $validated['refund_operation_uuid'],
            isset($validated['refund_cash_session_id']) ? CashSession::findOrFail($validated['refund_cash_session_id']) : null,
        );

        return redirect()->route('stays.show', $stay)->with('status', "Devolución de Bs {$refund->amount} registrada.");
    }
}
