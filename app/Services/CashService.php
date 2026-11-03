<?php

namespace App\Services;

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
use App\Models\CashRegister;
use App\Models\CashSession;
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
 * → stays → payments. Payments and refunds are idempotent on their operation UUID.
 * Authorization (permissions and branch access) is the caller's responsibility.
 */
class CashService
{
    public function __construct(
        private StayBalanceService $stayBalanceService,
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
     * Collect a payment for an active stay.
     *
     * A settlement may not exceed the outstanding balance. A deposit may leave the
     * guest in credit by at most the branch deposit limit (nights × agreed rate).
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function cobrarPago(
        Stay $stay,
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
            'stay_id' => $stay->id,
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
                $stay, $cashSession, $user, $paymentMethod, $paymentType, $amount, $operationUuid, $requestHash, $reference,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentPayment = Payment::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentPayment, $requestHash, $operationUuid);
        }
    }

    /**
     * Refund part or all of a confirmed payment through its original method.
     * Cash refunds leave the register and therefore need an open session of the
     * stay's branch.
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
     * Close a session against the cash physically counted. The difference is
     * attributed to the shared session, not to a person; above the branch
     * threshold the session waits for a supervisor review.
     *
     * @throws BusinessRuleException
     */
    public function cerrarSesion(CashSession $cashSession, User $user, string $declaredAmount, ?string $closingNotes = null): CashSession
    {
        $declaredAmount = (new Number($declaredAmount))->round(2);

        if ($declaredAmount < 0) {
            throw BusinessRuleException::importeNegativo();
        }

        return DB::transaction(function () use ($cashSession, $user, $declaredAmount, $closingNotes): CashSession {
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
        Stay $stay,
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
        $lockedStay = Stay::query()->with('branch')->lockForUpdate()->findOrFail($stay->id);

        if ($lockedSession->cashRegister->branch_id !== $lockedStay->branch_id) {
            throw BusinessRuleException::sesionDeOtraSucursal();
        }

        if ($lockedStay->status !== StayStatus::Active) {
            throw BusinessRuleException::estadiaNoActiva();
        }

        $this->verificarLimiteDePago($lockedStay, $paymentType, $amount);

        $payment = $lockedStay->payments()->create([
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

        $this->auditService->registrar('cash', 'payment.collected', $payment, $user, $lockedStay->branch_id, [
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
        $lockedPayment = Payment::query()->with('stay')->lockForUpdate()->findOrFail($payment->id);

        if ($lockedSession !== null && $lockedSession->cashRegister->branch_id !== $lockedPayment->stay->branch_id) {
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

        $this->auditService->registrar('cash', 'payment.refunded', $refund, $user, $lockedPayment->stay->branch_id, [
            'payment_id' => $lockedPayment->id,
            'amount' => $refund->amount,
            'reason' => $reason,
        ]);

        return $refund;
    }

    /**
     * @throws BusinessRuleException
     */
    private function bloquearSesionAbierta(CashSession $cashSession): CashSession
    {
        $lockedSession = CashSession::query()->with('cashRegister')->lockForUpdate()->findOrFail($cashSession->id);

        if (! $lockedSession->status->aceptaMovimientos()) {
            throw BusinessRuleException::sesionNoAbierta();
        }

        return $lockedSession;
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarLimiteDePago(Stay $stay, PaymentType $paymentType, Number $amount): void
    {
        $balance = $this->stayBalanceService->calcular($stay);

        if ($paymentType === PaymentType::Settlement) {
            if ($amount > $balance) {
                throw BusinessRuleException::pagoSuperaSaldo($balance > 0 ? $balance : new Number('0.00'));
            }

            return;
        }

        $depositLimitNights = $stay->branch->deposit_limit_nights ?? (int) config('lodging.deposit_limit_nights');
        $maximumDeposit = ($balance + new Number((string) $stay->rate_amount) * $depositLimitNights)->round(2);

        if ($amount > $maximumDeposit) {
            throw BusinessRuleException::anticipoSuperaTope($maximumDeposit > 0 ? $maximumDeposit : new Number('0.00'));
        }
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
