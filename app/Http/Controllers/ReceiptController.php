<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * The holder defaults to the primary guest and can be changed, for
     * example when a company pays.
     */
    public function create(Request $request, Payment $payment): View
    {
        $this->asegurarDeLaSucursalActiva($request, $payment->folio->branch_id);
        Gate::authorize('emitir', [Receipt::class, $payment]);

        $primaryGuest = $payment->folio->stay->guests()->wherePivot('is_primary', true)->first();

        return view('receipts.create', [
            'payment' => $payment->load('folio.stay.room'),
            'holderName' => $primaryGuest ? "{$primaryGuest->first_name} {$primaryGuest->last_name}" : '',
            'holderDocument' => $primaryGuest ? $this->formatearDocumento($primaryGuest) : '',
        ]);
    }

    public function store(Request $request, Payment $payment, ReceiptService $receiptService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $payment->folio->branch_id);
        Gate::authorize('emitir', [Receipt::class, $payment]);

        $validated = $request->validate([
            'holder_name' => ['required', 'string', 'max:255'],
            'holder_document' => ['nullable', 'string', 'max:50'],
        ], attributes: [
            'holder_name' => 'titular',
            'holder_document' => 'documento del titular',
        ]);

        $receipt = $receiptService->emitir($payment, $request->user(), $validated['holder_name'], $validated['holder_document'] ?? null);

        return redirect()->route('receipts.show', $receipt)->with('status', "Recibo {$receipt->numeroCompleto()} emitido.");
    }

    public function show(Request $request, Receipt $receipt): View
    {
        $this->asegurarDeLaSucursalActiva($request, $receipt->branch_id);
        Gate::authorize('ver', $receipt);

        return view('receipts.show', [
            'receipt' => $receipt->load(['branch', 'folio.stay.room', 'payment', 'issuedByUser', 'voidedByUser']),
        ]);
    }

    private function formatearDocumento(Guest $guest): string
    {
        return $guest->document_number.($guest->document_complement ? "-{$guest->document_complement}" : '');
    }
}
