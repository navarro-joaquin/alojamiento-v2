<?php

namespace App\Services;

use App\Enums\CashCountType;
use App\Enums\CashDenomination;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RefundStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\CashCount;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Stay;
use App\Models\User;
use BcMath\Number;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Every operation that moves money through a cash register.
 *
 * Rows are always locked in the canonical order: cash_registers → cash_sessions
 * → stays → folios → payments. Payments and refunds are idempotent on their operation UUID.
 * Authorization (permissions and branch access) is the caller's responsibility.
 */
class CashService
{
    public function __construct(
        private FolioBalanceService $folioBalanceService,
        private AuditService $auditService,
    ) {}

    /**
     * @throws BusinessRuleException
     */
    public function abrirSesion(CashRegister $cashRegister, User $user, string $openingAmount): CashSession
    {
        $openingAmount = new Number($openingAmount);

        if ($openingAmount < 0) {
            throw BusinessRuleException::importeNegativo();
        }

        try {
            return DB::transaction(function () use ($cashRegister, $user, $openingAmount): CashSession {
                $lockedRegister = CashRegister::query()->with('branch')->lockForUpdate()->findOrFail($cashRegister->id);

                if (! $lockedRegister->is_active) {
                    throw BusinessRuleException::cajaInactiva();
                }

                $hasUnclosedSession = $lockedRegister->sessions()
                    ->whereIn('status', [CashSessionStatus::Open, CashSessionStatus::Closing])
                    ->exists();

                if ($hasUnclosedSession) {
                    throw BusinessRuleException::cajaConSesionAbierta();
                }

                $cashSession = $lockedRegister->sessions()->create([
                    'opened_by' => $user->id,
                    'business_date' => $lockedRegister->branch->current_business_date,
                    'opened_at' => now(),
                    'opening_amount' => (string) $openingAmount->round(2),
                    'status' => CashSessionStatus::Open,
                ]);

                $this->auditService->registrar('cash', 'cash_session.opened', $cashSession, $user, $lockedRegister->branch_id, [
                    'opening_amount' => $cashSession->opening_amount,
                ]);

                return $cashSession;
            });
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::cajaConSesionAbierta();
        }
    }

    /**
     * Collect a payment on a folio: that of an active stay, or the debt left by
     * a completed one.
     *
     * A settlement may not exceed the outstanding balance. A deposit may leave the
     * guest in credit by at most the branch deposit limit (nights × agreed rate).
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function cobrarPago(
        Folio $folio,
        CashSession $cashSession,
        User $user,
        PaymentMethod $paymentMethod,
        PaymentType $paymentType,
        string $amount,
        string $operationUuid,
        ?string $reference = null,
    ): Payment {
        $amount = (new Number($amount))->round(2);

        if ($amount <= 0) {
            throw BusinessRuleException::importeNoPositivo();
        }

        $requestHash = $this->calcularHashSolicitud([
            'folio_id' => $folio->id,
            'cash_session_id' => $cashSession->id,
            'payment_method' => $paymentMethod->value,
            'payment_type' => $paymentType->value,
            'amount' => (string) $amount,
        ]);

        $existingPayment = Payment::query()->where('operation_uuid', $operationUuid)->first();

        if ($existingPayment !== null) {
            return $this->resolverReintento($existingPayment, $requestHash, $operationUuid);
        }

        try {
            return DB::transaction(fn (): Payment => $this->registrarPago(
                $folio, $cashSession, $user, $paymentMethod, $paymentType, $amount, $operationUuid, $requestHash, $reference,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentPayment = Payment::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentPayment, $requestHash, $operationUuid);
        }
    }

    /**
     * Refund part or all of a confirmed payment through its original method.
     * Only the folio's credit can be refunded, so a refund never creates debt:
     * a wrong charge is voided or discounted first. Cash refunds leave the
     * register and therefore need an open session of the folio's branch.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function devolverPago(
        Payment $payment,
        User $user,
        string $amount,
        string $reason,
        string $operationUuid,
        ?CashSession $cashSession = null,
    ): PaymentRefund {
        $amount = (new Number($amount))->round(2);

        if ($amount <= 0) {
            throw BusinessRuleException::importeNoPositivo();
        }

        $refundsCash = $payment->payment_method->afectaEfectivo();

        if ($refundsCash && $cashSession === null) {
            throw BusinessRuleException::devolucionEnEfectivoSinSesion();
        }

        $cashSession = $refundsCash ? $cashSession : null;

        $requestHash = $this->calcularHashSolicitud([
            'payment_id' => $payment->id,
            'cash_session_id' => $cashSession?->id,
            'amount' => (string) $amount,
        ]);

        $existingRefund = PaymentRefund::query()->where('operation_uuid', $operationUuid)->first();

        if ($existingRefund !== null) {
            return $this->resolverReintento($existingRefund, $requestHash, $operationUuid);
        }

        try {
            return DB::transaction(fn (): PaymentRefund => $this->registrarDevolucion(
                $payment, $cashSession, $user, $amount, $reason, $operationUuid, $requestHash,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentRefund = PaymentRefund::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentRefund, $requestHash, $operationUuid);
        }
    }

    /**
     * Count the cash of an open session without closing it, for example at a
     * shift change. The count only records the difference; it moves no money.
     *
     * @param  array<int, int>  $denominations  quantity by value in centavos
     *
     * @throws BusinessRuleException
     */
    public function registrarArqueo(CashSession $cashSession, User $user, string $declaredAmount, array $denominations = [], ?string $notes = null): CashCount
    {
        $declaredAmount = $this->verificarEfectivoContado($declaredAmount, $denominations);

        return DB::transaction(function () use ($cashSession, $user, $declaredAmount, $denominations, $notes): CashCount {
            $lockedSession = $this->bloquearSesionAbierta($cashSession);
            $expectedAmount = $this->calcularEfectivoEsperado($lockedSession);

            $cashCount = $lockedSession->cashCounts()->create([
                'counted_by' => $user->id,
                'count_type' => CashCountType::Partial,
                'expected_amount' => (string) $expectedAmount,
                'declared_amount' => (string) $declaredAmount,
                'difference_amount' => (string) ($declaredAmount - $expectedAmount),
                'denominations' => $denominations ?: null,
                'notes' => $notes,
                'counted_at' => now(),
            ]);

            $this->auditService->registrar('cash', 'cash_count.recorded', $cashCount, $user, $lockedSession->cashRegister->branch_id, [
                'cash_session_id' => $lockedSession->id,
                'expected_amount' => $cashCount->expected_amount,
                'declared_amount' => $cashCount->declared_amount,
                'difference_amount' => $cashCount->difference_amount,
            ]);

            return $cashCount;
        });
    }

    /**
     * Close a session against the cash physically counted. The difference is
     * attributed to the shared session, not to a person; above the branch
     * threshold the session waits for a supervisor review.
     *
     * @param  array<int, int>  $denominations  quantity by value in centavos
     *
     * @throws BusinessRuleException
     */
    public function cerrarSesion(CashSession $cashSession, User $user, string $declaredAmount, ?string $closingNotes = null, array $denominations = []): CashSession
    {
        $declaredAmount = $this->verificarEfectivoContado($declaredAmount, $denominations);

        return DB::transaction(function () use ($cashSession, $user, $declaredAmount, $closingNotes, $denominations): CashSession {
            $lockedRegister = CashRegister::query()->with('branch')->lockForUpdate()->findOrFail($cashSession->cash_register_id);
            $lockedSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if (! $lockedSession->status->aceptaMovimientos()) {
                throw BusinessRuleException::sesionNoAbierta();
            }

            $expectedAmount = $this->calcularEfectivoEsperado($lockedSession);
            $differenceAmount = $declaredAmount - $expectedAmount;
            $threshold = new Number((string) ($lockedRegister->branch->cash_difference_threshold
                ?? config('lodging.cash_difference_threshold')));

            $absoluteDifference = $differenceAmount < 0 ? $differenceAmount * -1 : $differenceAmount;

            $lockedSession->update([
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected_amount' => (string) $expectedAmount,
                'declared_amount' => (string) $declaredAmount,
                'difference_amount' => (string) $differenceAmount,
                'closing_notes' => $closingNotes,
                'status' => $absoluteDifference > $threshold ? CashSessionStatus::PendingReview : CashSessionStatus::Closed,
            ]);

            $lockedSession->cashCounts()->create([
                'counted_by' => $user->id,
                'count_type' => CashCountType::Closing,
                'expected_amount' => $lockedSession->expected_amount,
                'declared_amount' => $lockedSession->declared_amount,
                'difference_amount' => $lockedSession->difference_amount,
                'denominations' => $denominations ?: null,
                'notes' => $closingNotes,
                'counted_at' => $lockedSession->closed_at,
            ]);

            $this->auditService->registrar('cash', 'cash_session.closed', $lockedSession, $user, $lockedRegister->branch_id, [
                'expected_amount' => $lockedSession->expected_amount,
                'declared_amount' => $lockedSession->declared_amount,
                'difference_amount' => $lockedSession->difference_amount,
                'status' => $lockedSession->status->value,
            ]);

            return $lockedSession;
        });
    }

    /**
     * A supervisor other than whoever closed the session accepts its
     * difference, with notes, and the session is finally closed.
     *
     * @throws BusinessRuleException
     */
    public function revisarSesion(CashSession $cashSession, User $user, string $reviewNotes): CashSession
    {
        return DB::transaction(function () use ($cashSession, $user, $reviewNotes): CashSession {
            $lockedRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashSession->cash_register_id);
            $lockedSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if ($lockedSession->status !== CashSessionStatus::PendingReview) {
                throw BusinessRuleException::sesionNoPendienteDeRevision();
            }

            if ($lockedSession->closed_by === $user->id) {
                throw BusinessRuleException::revisionPorQuienCerro();
            }

            $lockedSession->update([
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
                'status' => CashSessionStatus::Closed,
            ]);

            $this->auditService->registrar('cash', 'cash_session.reviewed', $lockedSession, $user, $lockedRegister->branch_id, [
                'difference_amount' => $lockedSession->difference_amount,
                'notes' => $reviewNotes,
            ]);

            return $lockedSession;
        });
    }

    /**
     * Opening amount plus cash in minus cash out, counting only movements that
     * move physical cash.
     */
    public function calcularEfectivoEsperado(CashSession $cashSession): Number
    {
        $totals = $cashSession->movements()
            ->where('affects_cash', true)
            ->selectRaw('direction, SUM(amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $cashIn = new Number((string) ($totals[CashMovementDirection::In->value] ?? '0'));
        $cashOut = new Number((string) ($totals[CashMovementDirection::Out->value] ?? '0'));

        return (new Number((string) $cashSession->opening_amount) + $cashIn - $cashOut)->round(2);
    }

    private function registrarPago(
        Folio $folio,
        CashSession $cashSession,
        User $user,
        PaymentMethod $paymentMethod,
        PaymentType $paymentType,
        Number $amount,
        string $operationUuid,
        string $requestHash,
        ?string $reference,
    ): Payment {
        $lockedSession = $this->bloquearSesionAbierta($cashSession);
        $lockedFolio = $this->bloquearFolio($folio);
        $lockedStay = $lockedFolio->stay;

        $concurrentPayment = Payment::query()->where('operation_uuid', $operationUuid)->first();

        if ($concurrentPayment !== null) {
            return $this->resolverReintento($concurrentPayment, $requestHash, $operationUuid);
        }

        if ($lockedSession->cashRegister->branch_id !== $lockedFolio->branch_id) {
            throw BusinessRuleException::sesionDeOtraSucursal();
        }

        if ($lockedStay->status === StayStatus::Completed && $paymentType !== PaymentType::Settlement) {
            throw BusinessRuleException::estadiaFinalizadaSoloAdmiteSaldo();
        }

        if (! in_array($lockedStay->status, [StayStatus::Active, StayStatus::Completed], true)) {
            throw BusinessRuleException::estadiaNoActiva();
        }

        $this->verificarLimiteDePago($lockedFolio, $paymentType, $amount);

        $payment = $lockedFolio->payments()->create([
            'cash_session_id' => $lockedSession->id,
            'created_by' => $user->id,
            'payment_method' => $paymentMethod,
            'payment_type' => $paymentType,
            'amount' => (string) $amount,
            'reference' => $reference,
            'operation_uuid' => $operationUuid,
            'request_hash' => $requestHash,
            'paid_at' => now(),
            'status' => PaymentStatus::Confirmed,
        ]);

        $payment->cashMovements()->create([
            'cash_session_id' => $lockedSession->id,
            'performed_by' => $user->id,
            'movement_type' => CashMovementType::Payment,
            'payment_method' => $paymentMethod,
            'direction' => CashMovementDirection::In,
            'amount' => (string) $amount,
            'affects_cash' => $paymentMethod->afectaEfectivo(),
            'operation_uuid' => $operationUuid,
            'description' => "Cobro de la estadía #{$lockedStay->id}",
            'occurred_at' => $payment->paid_at,
        ]);

        $this->auditService->registrar('cash', 'payment.collected', $payment, $user, $lockedFolio->branch_id, [
            'folio_id' => $lockedFolio->id,
            'stay_id' => $lockedStay->id,
            'amount' => $payment->amount,
            'payment_method' => $paymentMethod->value,
            'payment_type' => $paymentType->value,
        ]);

        return $payment;
    }

    private function registrarDevolucion(
        Payment $payment,
        ?CashSession $cashSession,
        User $user,
        Number $amount,
        string $reason,
        string $operationUuid,
        string $requestHash,
    ): PaymentRefund {
        $lockedSession = $cashSession !== null ? $this->bloquearSesionAbierta($cashSession) : null;
        $lockedFolio = $this->bloquearFolio($payment->folio);
        $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id)->setRelation('folio', $lockedFolio);

        $concurrentRefund = PaymentRefund::query()->where('operation_uuid', $operationUuid)->first();

        if ($concurrentRefund !== null) {
            return $this->resolverReintento($concurrentRefund, $requestHash, $operationUuid);
        }

        if ($lockedSession !== null && $lockedSession->cashRegister->branch_id !== $lockedFolio->branch_id) {
            throw BusinessRuleException::sesionDeOtraSucursal();
        }

        if ($lockedPayment->status !== PaymentStatus::Confirmed) {
            throw BusinessRuleException::pagoNoConfirmado();
        }

        $alreadyRefunded = new Number((string) ($lockedPayment->refunds()->confirmadas()->sum('amount') ?: '0'));
        $refundableAmount = (new Number((string) $lockedPayment->amount) - $alreadyRefunded)->round(2);

        if ($amount > $refundableAmount) {
            throw BusinessRuleException::devolucionSuperaReembolsable($refundableAmount);
        }

        $creditAmount = $this->folioBalanceService->calcular($lockedFolio) * -1;

        if ($amount > $creditAmount) {
            throw BusinessRuleException::devolucionSuperaSaldoAFavor($creditAmount > 0 ? $creditAmount : new Number('0.00'));
        }

        $refund = $lockedPayment->refunds()->create([
            'cash_session_id' => $lockedSession?->id,
            'processed_by' => $user->id,
            'amount' => (string) $amount,
            'refund_method' => $lockedPayment->payment_method,
            'status' => RefundStatus::Confirmed,
            'reason' => $reason,
            'operation_uuid' => $operationUuid,
            'request_hash' => $requestHash,
            'refunded_at' => now(),
        ]);

        if ($lockedSession !== null) {
            $refund->cashMovements()->create([
                'cash_session_id' => $lockedSession->id,
                'performed_by' => $user->id,
                'movement_type' => CashMovementType::Refund,
                'payment_method' => $lockedPayment->payment_method,
                'direction' => CashMovementDirection::Out,
                'amount' => (string) $amount,
                'affects_cash' => true,
                'operation_uuid' => $operationUuid,
                'description' => "Devolución del pago #{$lockedPayment->id}",
                'occurred_at' => $refund->refunded_at,
            ]);
        }

        $this->auditService->registrar('cash', 'payment.refunded', $refund, $user, $lockedFolio->branch_id, [
            'payment_id' => $lockedPayment->id,
            'amount' => $refund->amount,
            'reason' => $reason,
        ]);

        return $refund;
    }

    /**
     * Lock a session that still accepts movements, with its register loaded.
     *
     * @throws BusinessRuleException
     */
    public function bloquearSesionAbierta(CashSession $cashSession): CashSession
    {
        $lockedSession = CashSession::query()->with('cashRegister')->lockForUpdate()->findOrFail($cashSession->id);

        if (! $lockedSession->status->aceptaMovimientos()) {
            throw BusinessRuleException::sesionNoAbierta();
        }

        return $lockedSession;
    }

    /**
     * Lock a folio after the stay that owns it, with both loaded.
     */
    private function bloquearFolio(Folio $folio): Folio
    {
        $lockedStay = $folio->stay_id !== null
            ? Stay::query()->with('branch')->lockForUpdate()->findOrFail($folio->stay_id)
            : null;

        return Folio::query()->with('branch')->lockForUpdate()->findOrFail($folio->id)->setRelation('stay', $lockedStay);
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarLimiteDePago(Folio $folio, PaymentType $paymentType, Number $amount): void
    {
        $balance = $this->folioBalanceService->calcular($folio);

        if ($paymentType === PaymentType::Settlement) {
            if ($amount > $balance) {
                throw BusinessRuleException::pagoSuperaSaldo($balance > 0 ? $balance : new Number('0.00'));
            }

            return;
        }

        $depositLimitNights = $folio->branch->deposit_limit_nights ?? (int) config('lodging.deposit_limit_nights');
        $maximumDeposit = ($balance + new Number((string) $folio->stay->rate_amount) * $depositLimitNights)->round(2);

        if ($amount > $maximumDeposit) {
            throw BusinessRuleException::anticipoSuperaTope($maximumDeposit > 0 ? $maximumDeposit : new Number('0.00'));
        }
    }

    /**
     * When the bills and coins are detailed, they must add up to the amount.
     *
     * @param  array<int, int>  $denominations  quantity by value in centavos
     *
     * @throws BusinessRuleException
     */
    private function verificarEfectivoContado(string $declaredAmount, array $denominations): Number
    {
        $declaredAmount = (new Number($declaredAmount))->round(2);

        if ($declaredAmount < 0) {
            throw BusinessRuleException::importeNegativo();
        }

        if ($denominations !== []) {
            $countedAmount = CashDenomination::sumar($denominations);

            if ($countedAmount != $declaredAmount) {
                throw BusinessRuleException::denominacionesNoCoinciden($countedAmount, $declaredAmount);
            }
        }

        return $declaredAmount;
    }

    /**
     * @param  array<string, int|string|null>  $requestData
     */
    private function calcularHashSolicitud(array $requestData): string
    {
        return hash('sha256', json_encode($requestData, JSON_THROW_ON_ERROR));
    }

    /**
     * @template TOperation of Payment|PaymentRefund
     *
     * @param  TOperation  $existingOperation
     * @return TOperation
     *
     * @throws IdempotencyConflictException
     */
    private function resolverReintento(Model $existingOperation, string $requestHash, string $operationUuid): Model
    {
        if (! hash_equals($existingOperation->request_hash, $requestHash)) {
            throw IdempotencyConflictException::paraOperacion($operationUuid);
        }

        return $existingOperation;
    }
}
