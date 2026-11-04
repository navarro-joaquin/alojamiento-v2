<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Internal receipts of payments, numbered per branch without gaps or
 * duplicates, and the external invoice number of each payment.
 *
 * Rows are locked in the canonical order: payments → receipt_sequences.
 * Authorization is the caller's responsibility.
 */
class ReceiptService
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /**
     * Issue the receipt of a confirmed payment for its whole amount. A payment
     * has at most one receipt in force; a voided one can be replaced.
     *
     * @throws BusinessRuleException
     */
    public function emitir(Payment $payment, User $user, string $holderName, ?string $holderDocument = null): Receipt
    {
        return DB::transaction(function () use ($payment, $user, $holderName, $holderDocument): Receipt {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $folio = Folio::query()->with('branch')->findOrFail($lockedPayment->folio_id);

            if ($lockedPayment->status !== PaymentStatus::Confirmed) {
                throw BusinessRuleException::reciboDePagoNoConfirmado();
            }

            $this->verificarSinReciboVigente($lockedPayment);

            $sequence = $this->bloquearSecuencia($folio->branch);

            $receipt = Receipt::create([
                'branch_id' => $folio->branch_id,
                'folio_id' => $folio->id,
                'payment_id' => $lockedPayment->id,
                'prefix' => $sequence->prefix,
                'number' => $sequence->next_number,
                'holder_name' => $holderName,
                'holder_document' => $holderDocument,
                'amount' => $lockedPayment->amount,
                'status' => ReceiptStatus::Issued,
                'issued_by' => $user->id,
                'issued_at' => now(),
            ]);

            $sequence->increment('next_number');

            $this->auditService->registrar('cash', 'receipt.issued', $receipt, $user, $folio->branch_id, [
                'payment_id' => $lockedPayment->id,
                'number' => $receipt->numeroCompleto(),
                'amount' => $receipt->amount,
            ]);

            return $receipt;
        });
    }

    /**
     * Void a receipt with a reason. Its number is never reused.
     *
     * @throws BusinessRuleException
     */
    public function anular(Receipt $receipt, User $user, string $reason): Receipt
    {
        return DB::transaction(function () use ($receipt, $user, $reason): Receipt {
            Payment::query()->lockForUpdate()->findOrFail($receipt->payment_id);
            $lockedReceipt = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->status !== ReceiptStatus::Issued) {
                throw BusinessRuleException::reciboYaAnulado();
            }

            $lockedReceipt->update([
                'status' => ReceiptStatus::Voided,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->auditService->registrar('cash', 'receipt.voided', $lockedReceipt, $user, $lockedReceipt->branch_id, [
                'number' => $lockedReceipt->numeroCompleto(),
                'reason' => $reason,
            ]);

            return $lockedReceipt;
        });
    }

    /**
     * Record (or clear) the number of the invoice issued for the payment in
     * the external fiscal system.
     */
    public function registrarFacturaExterna(Payment $payment, User $user, ?string $externalInvoiceNumber): Payment
    {
        return DB::transaction(function () use ($payment, $user, $externalInvoiceNumber): Payment {
            $lockedPayment = Payment::query()->with('folio')->lockForUpdate()->findOrFail($payment->id);
            $previousNumber = $lockedPayment->external_invoice_number;

            $lockedPayment->update(['external_invoice_number' => $externalInvoiceNumber]);

            $this->auditService->registrar('cash', 'payment.external_invoice_recorded', $lockedPayment, $user, $lockedPayment->folio->branch_id, [
                'previous_number' => $previousNumber,
                'external_invoice_number' => $externalInvoiceNumber,
            ]);

            return $lockedPayment;
        });
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarSinReciboVigente(Payment $payment): void
    {
        $issuedReceipt = $payment->receipts()->where('status', ReceiptStatus::Issued)->first();

        if ($issuedReceipt !== null) {
            throw BusinessRuleException::pagoConReciboVigente($issuedReceipt->numeroCompleto());
        }
    }

    /**
     * Lock the branch sequence, creating it on first use with the branch code
     * as prefix. The insert is ignored if another transaction created it.
     */
    private function bloquearSecuencia(Branch $branch): ReceiptSequence
    {
        $sequence = ReceiptSequence::query()->where('branch_id', $branch->id)->lockForUpdate()->first();

        if ($sequence !== null) {
            return $sequence;
        }

        ReceiptSequence::query()->insertOrIgnore([
            'branch_id' => $branch->id,
            'prefix' => $branch->code,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ReceiptSequence::query()->where('branch_id', $branch->id)->lockForUpdate()->firstOrFail();
    }
}
