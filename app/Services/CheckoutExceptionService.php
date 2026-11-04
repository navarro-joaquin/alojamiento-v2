<?php

namespace App\Services;

use App\Enums\CheckoutExceptionStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CheckoutException;
use App\Models\Stay;
use App\Models\User;
use BcMath\Number;
use Illuminate\Support\Facades\DB;

/**
 * Checkout with debt: an operator requests it for the current debt, someone
 * else with the approval permission authorizes it for a limited time, and a
 * single check-out consumes it, recorded by someone other than the approver.
 *
 * Rows are locked in the canonical order: stays → (rooms → payments) →
 * checkout_exceptions. Authorization is the caller's responsibility.
 */
class CheckoutExceptionService
{
    public function __construct(
        private FolioBalanceService $folioBalanceService,
        private AuditService $auditService,
    ) {}

    /**
     * Request an exception for the current debt. A pending request, or an
     * approval that expired or no longer matches the debt, is replaced.
     *
     * @throws BusinessRuleException
     */
    public function solicitar(Stay $stay, User $user, string $reason): CheckoutException
    {
        return DB::transaction(function () use ($stay, $user, $reason): CheckoutException {
            $lockedStay = $this->bloquearEstadiaActiva($stay);
            $balance = $this->calcularDeuda($lockedStay);

            $openException = $lockedStay->checkoutExceptions()->abiertas()->lockForUpdate()->first();

            if ($openException !== null) {
                if ($openException->estaVigente() && new Number($openException->outstanding_amount) == $balance) {
                    throw BusinessRuleException::salidaConDeudaYaAutorizada();
                }

                $openException->update([
                    'status' => $openException->status === CheckoutExceptionStatus::Approved && ! $openException->estaVigente()
                        ? CheckoutExceptionStatus::Expired
                        : CheckoutExceptionStatus::Revoked,
                    'resolved_by' => $user->id,
                    'resolved_at' => now(),
                    'resolution_notes' => 'Reemplazada por una nueva solicitud.',
                ]);
            }

            $checkoutException = $lockedStay->checkoutExceptions()->create([
                'requested_by' => $user->id,
                'outstanding_amount' => (string) $balance,
                'reason' => $reason,
                'status' => CheckoutExceptionStatus::Requested,
                'requested_at' => now(),
            ]);

            $this->auditService->registrar('reception', 'checkout_exception.requested', $checkoutException, $user, $lockedStay->branch_id, [
                'stay_id' => $lockedStay->id,
                'outstanding_amount' => $checkoutException->outstanding_amount,
                'replaced_exception_id' => $openException?->id,
            ]);

            return $checkoutException;
        });
    }

    /**
     * @throws BusinessRuleException
     */
    public function aprobar(CheckoutException $checkoutException, User $user, ?string $notes = null): CheckoutException
    {
        return DB::transaction(function () use ($checkoutException, $user, $notes): CheckoutException {
            $lockedStay = $this->bloquearEstadiaActiva($checkoutException->stay);
            $lockedException = CheckoutException::query()->lockForUpdate()->findOrFail($checkoutException->id);

            if ($lockedException->status !== CheckoutExceptionStatus::Requested) {
                throw BusinessRuleException::solicitudNoPendiente();
            }

            if ($lockedException->requested_by === $user->id) {
                throw BusinessRuleException::aprobacionPorElSolicitante();
            }

            $balance = $this->calcularDeuda($lockedStay);
            $authorizedAmount = new Number($lockedException->outstanding_amount);

            if ($balance != $authorizedAmount) {
                throw BusinessRuleException::deudaDistintaDeLaAutorizada($authorizedAmount, $balance);
            }

            $lockedException->update([
                'status' => CheckoutExceptionStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'expires_at' => now()->addMinutes(config('lodging.checkout_exception_minutes')),
                'resolution_notes' => $notes,
            ]);

            $this->auditService->registrar('reception', 'checkout_exception.approved', $lockedException, $user, $lockedStay->branch_id, [
                'stay_id' => $lockedStay->id,
                'outstanding_amount' => $lockedException->outstanding_amount,
                'expires_at' => $lockedException->expires_at->toIso8601String(),
            ]);

            return $lockedException;
        });
    }

    /**
     * Reject a pending request or revoke an approval that was not used yet.
     *
     * @throws BusinessRuleException
     */
    public function rechazar(CheckoutException $checkoutException, User $user, string $notes): CheckoutException
    {
        return DB::transaction(function () use ($checkoutException, $user, $notes): CheckoutException {
            $lockedStay = Stay::query()->lockForUpdate()->findOrFail($checkoutException->stay_id);
            $lockedException = CheckoutException::query()->lockForUpdate()->findOrFail($checkoutException->id);

            $newStatus = match ($lockedException->status) {
                CheckoutExceptionStatus::Requested => CheckoutExceptionStatus::Rejected,
                CheckoutExceptionStatus::Approved => CheckoutExceptionStatus::Revoked,
                default => throw BusinessRuleException::excepcionNoAbierta(),
            };

            $lockedException->update([
                'status' => $newStatus,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ]);

            $this->auditService->registrar('reception', "checkout_exception.{$newStatus->value}", $lockedException, $user, $lockedStay->branch_id, [
                'stay_id' => $lockedStay->id,
                'notes' => $notes,
            ]);

            return $lockedException;
        });
    }

    /**
     * Consume the stay's approval inside the check-out transaction, which
     * already holds the stay lock. The approval must be valid, match the
     * current debt exactly and not have been granted by the operator.
     *
     * @throws BusinessRuleException
     */
    public function consumir(Stay $lockedStay, User $user, Number $balance): CheckoutException
    {
        $approvedException = $lockedStay->checkoutExceptions()
            ->where('status', CheckoutExceptionStatus::Approved)
            ->lockForUpdate()
            ->first();

        if ($approvedException === null) {
            throw BusinessRuleException::salidaConDeuda($balance);
        }

        if (! $approvedException->estaVigente()) {
            throw BusinessRuleException::autorizacionDeSalidaVencida();
        }

        $authorizedAmount = new Number($approvedException->outstanding_amount);

        if ($balance != $authorizedAmount) {
            throw BusinessRuleException::deudaDistintaDeLaAutorizada($authorizedAmount, $balance);
        }

        if ($approvedException->approved_by === $user->id) {
            throw BusinessRuleException::salidaRegistradaPorQuienAutorizo();
        }

        $approvedException->update([
            'status' => CheckoutExceptionStatus::Used,
            'used_at' => now(),
        ]);

        return $approvedException;
    }

    /**
     * @throws BusinessRuleException
     */
    private function bloquearEstadiaActiva(Stay $stay): Stay
    {
        $lockedStay = Stay::query()->lockForUpdate()->findOrFail($stay->id);

        if ($lockedStay->status !== StayStatus::Active) {
            throw BusinessRuleException::estadiaNoActiva();
        }

        return $lockedStay;
    }

    /**
     * @throws BusinessRuleException
     */
    private function calcularDeuda(Stay $lockedStay): Number
    {
        $balance = $this->folioBalanceService->calcular($lockedStay->folio);

        if ($balance <= 0) {
            throw BusinessRuleException::estadiaSinDeuda();
        }

        return $balance;
    }
}
