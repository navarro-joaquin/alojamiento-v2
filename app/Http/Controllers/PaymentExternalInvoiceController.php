<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Number of the invoice issued for a payment in the external fiscal system.
 */
class PaymentExternalInvoiceController extends Controller
{
    public function update(Request $request, Payment $payment, ReceiptService $receiptService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $payment->folio->branch_id);
        Gate::authorize('registrarFacturaExterna', $payment);

        $validated = $request->validate([
            'external_invoice_number' => ['nullable', 'string', 'max:50'],
        ], attributes: ['external_invoice_number' => 'número de factura']);

        $receiptService->registrarFacturaExterna($payment, $request->user(), $validated['external_invoice_number'] ?? null);

        return redirect()->route('stays.show', $payment->folio->stay)->with('status', 'Número de factura guardado.');
    }
}
