<?php

namespace App\Services;

use App\Enums\ChargeDirection;
use App\Models\Folio;
use App\Models\PaymentRefund;
use BcMath\Number;

/**
 * Single source of truth for what a folio owes.
 *
 * balance = confirmed debits - confirmed credits - confirmed payments + confirmed refunds
 *
 * A positive balance is debt; a negative balance is credit in favour of the
 * holder. Callers that act on the result must hold a lock on the folio, or on
 * the stay that owns it.
 */
class FolioBalanceService
{
    public function calcular(Folio $folio): Number
    {
        $chargeTotals = $folio->charges()
            ->confirmados()
            ->selectRaw('direction, SUM(total_amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $debits = new Number((string) ($chargeTotals[ChargeDirection::Debit->value] ?? '0'));
        $credits = new Number((string) ($chargeTotals[ChargeDirection::Credit->value] ?? '0'));

        $payments = new Number((string) ($folio->payments()->confirmados()->sum('amount') ?: '0'));

        $refunds = new Number((string) (PaymentRefund::query()
            ->confirmadas()
            ->whereHas('payment', fn ($query) => $query->whereBelongsTo($folio))
            ->sum('amount') ?: '0'));

        return ($debits - $credits - $payments + $refunds)->round(2);
    }
}
