<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VoidedReceiptController extends Controller
{
    public function store(Request $request, Receipt $receipt, ReceiptService $receiptService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $receipt->branch_id);
        Gate::authorize('anular', $receipt);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ], attributes: ['void_reason' => 'motivo de la anulación']);

        $voidedReceipt = $receiptService->anular($receipt, $request->user(), $validated['void_reason']);

        return redirect()->route('receipts.show', $voidedReceipt)->with('status', "Recibo {$voidedReceipt->numeroCompleto()} anulado.");
    }
}
