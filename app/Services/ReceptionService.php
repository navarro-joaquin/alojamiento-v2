<?php

namespace App\Services;

use App\Enums\HousekeepingStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\CashSession;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use BcMath\Number;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Check-in, room change and check-out.
 *
 * Rows are locked in the canonical order: cash_sessions → stays → rooms
 * (several rooms by id) → payments → checkout_exceptions. Authorization is the caller's responsibility.
 */
class ReceptionService
{
    public function __construct(
        private RateService $rateService,
        private ChargeService $chargeService,
        private HousekeepingService $housekeepingService,
        private StayBalanceService $stayBalanceService,
        private CashService $cashService,
        private CheckoutExceptionService $checkoutExceptionService,
        private AuditService $auditService,
    ) {}

    /**
     * Register a walk-in: resolve the rate, create the stay with its guests,
     * charge the first night on the branch business date and mark the room dirty.
     *
     * @param  list<Guest>  $companions
     *
     * @throws BusinessRuleException
     */
    public function registrarIngreso(
        Room $room,
        User $user,
        Guest $primaryGuest,
        array $companions = [],
        int $adults = 1,
        int $children = 0,
        ?CarbonInterface $expectedCheckOutAt = null,
        ?string $notes = null,
    ): Stay {
        $companionIds = collect($companions)->map(fn (Guest $guest): int => $guest->id);

        if ($companionIds->contains($primaryGuest->id) || $companionIds->duplicates()->isNotEmpty()) {
            throw BusinessRuleException::huespedRepetido();
        }

        try {
            return DB::transaction(function () use ($room, $user, $primaryGuest, $companionIds, $adults, $children, $expectedCheckOutAt, $notes): Stay {
                $lockedRoom = Room::query()->with('branch')->lockForUpdate()->findOrFail($room->id);
                $this->verificarHabitacionAsignable($lockedRoom, $adults + $children);

                $businessDate = $lockedRoom->branch->current_business_date;
                $roomRate = $this->rateService->resolverTarifa($lockedRoom, $adults + $children, $businessDate);

                $stay = Stay::create([
                    'branch_id' => $lockedRoom->branch_id,
                    'room_id' => $lockedRoom->id,
                    'room_rate_id' => $roomRate->id,
                    'created_by' => $user->id,
                    'check_in_at' => now(),
                    'expected_check_out_at' => $expectedCheckOutAt,
                    'status' => StayStatus::Active,
                    'rate_amount' => $roomRate->amount,
                    'adults' => $adults,
                    'children' => $children,
                    'notes' => $notes,
                ]);

                $stay->roomAssignments()->create([
                    'room_id' => $lockedRoom->id,
                    'rate_amount' => $stay->rate_amount,
                    'assigned_from' => $stay->check_in_at,
                    'changed_by' => $user->id,
                ]);

                $stay->guests()->attach($primaryGuest->id, ['is_primary' => true]);
                $stay->guests()->attach($companionIds->all(), ['is_primary' => false]);

                $this->chargeService->registrarNocheAlojamiento($stay, $user, $businessDate);
                $this->housekeepingService->cambiarEstadoLimpieza($lockedRoom, HousekeepingStatus::Dirty, $user, 'Ingreso de huéspedes');

                $this->auditService->registrar('reception', 'stay.checked_in', $stay, $user, $stay->branch_id, [
                    'room_id' => $lockedRoom->id,
                    'rate_amount' => $stay->rate_amount,
                ]);

                return $stay;
            });
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::habitacionOcupada();
        }
    }

    /**
     * Move an active stay to another room of the same branch. Without an agreed
     * rate, the rate of the new room on the current business date applies.
     *
     * @throws BusinessRuleException
     */
    public function cambiarHabitacion(Stay $stay, Room $newRoom, User $user, string $reason, ?string $agreedRateAmount = null): Stay
    {
        try {
            return DB::transaction(function () use ($stay, $newRoom, $user, $reason, $agreedRateAmount): Stay {
                $lockedStay = $this->bloquearEstadiaActiva($stay);

                if ($lockedStay->room_id === $newRoom->id) {
                    throw BusinessRuleException::mismaHabitacion();
                }

                $lockedRooms = $this->bloquearHabitaciones([$lockedStay->room_id, $newRoom->id]);
                $previousRoom = $lockedRooms[$lockedStay->room_id];
                $destinationRoom = $lockedRooms[$newRoom->id];

                if ($destinationRoom->branch_id !== $lockedStay->branch_id) {
                    throw BusinessRuleException::habitacionDeOtraSucursal();
                }

                $this->verificarHabitacionAsignable($destinationRoom, $lockedStay->adults + $lockedStay->children);

                $roomRate = $agreedRateAmount === null
                    ? $this->rateService->resolverTarifa($destinationRoom, $lockedStay->adults + $lockedStay->children, $lockedStay->branch->current_business_date)
                    : null;
                $rateAmount = $roomRate?->amount ?? (string) (new Number($agreedRateAmount))->round(2);

                if (new Number($rateAmount) <= 0) {
                    throw BusinessRuleException::importeNoPositivo();
                }

                $changedAt = now();
                $lockedStay->roomAssignments()->whereNull('assigned_to')->update(['assigned_to' => $changedAt]);
                $lockedStay->roomAssignments()->create([
                    'room_id' => $destinationRoom->id,
                    'rate_amount' => $rateAmount,
                    'assigned_from' => $changedAt,
                    'changed_by' => $user->id,
                    'reason' => $reason,
                ]);

                $lockedStay->update([
                    'room_id' => $destinationRoom->id,
                    'room_rate_id' => $roomRate?->id,
                    'rate_amount' => $rateAmount,
                ]);

                $this->housekeepingService->cambiarEstadoLimpieza($previousRoom, HousekeepingStatus::Dirty, $user, 'Cambio de habitación');
                $this->housekeepingService->cambiarEstadoLimpieza($destinationRoom, HousekeepingStatus::Dirty, $user, 'Cambio de habitación');

                $this->auditService->registrar('reception', 'stay.room_changed', $lockedStay, $user, $lockedStay->branch_id, [
                    'from_room_id' => $previousRoom->id,
                    'to_room_id' => $destinationRoom->id,
                    'rate_amount' => $rateAmount,
                    'reason' => $reason,
                ]);

                return $lockedStay;
            });
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::habitacionOcupada();
        }
    }

    /**
     * Complete an active stay. Any credit in favour of the guest is refunded in
     * the same transaction (cash refunds need the open session). A stay with
     * debt needs a valid checkout exception for exactly that debt, which the
     * check-out consumes; the debt stays on the completed stay.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function registrarSalida(Stay $stay, User $user, ?CashSession $cashSession = null): Stay
    {
        return DB::transaction(function () use ($stay, $user, $cashSession): Stay {
            if ($cashSession !== null) {
                CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);
            }

            $lockedStay = $this->bloquearEstadiaActiva($stay);
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($lockedStay->room_id);

            $balance = $this->stayBalanceService->calcular($lockedStay);

            $checkoutException = $balance > 0
                ? $this->checkoutExceptionService->consumir($lockedStay, $user, $balance)
                : null;

            $refundedAmount = $balance < 0
                ? $this->devolverSaldoAFavor($lockedStay, $user, $balance * -1, $cashSession)
                : new Number('0.00');

            $checkedOutAt = now();
            $lockedStay->roomAssignments()->whereNull('assigned_to')->update(['assigned_to' => $checkedOutAt]);
            $lockedStay->update([
                'status' => StayStatus::Completed,
                'check_out_at' => $checkedOutAt,
            ]);

            $this->housekeepingService->cambiarEstadoLimpieza($lockedRoom, HousekeepingStatus::Dirty, $user, 'Salida de huéspedes');

            $this->auditService->registrar('reception', 'stay.checked_out', $lockedStay, $user, $lockedStay->branch_id, [
                'room_id' => $lockedRoom->id,
                'refunded_amount' => (string) $refundedAmount,
                'outstanding_amount' => $checkoutException?->outstanding_amount,
                'checkout_exception_id' => $checkoutException?->id,
            ]);

            return $lockedStay;
        });
    }

    /**
     * Refund the guest's credit starting with the most recent payments, each
     * through its own method.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    private function devolverSaldoAFavor(Stay $stay, User $user, Number $creditAmount, ?CashSession $cashSession): Number
    {
        $remainingAmount = $creditAmount;

        $payments = $stay->payments()
            ->confirmados()
            ->withSum(['refunds as refunded_amount' => fn ($query) => $query->confirmadas()], 'amount')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        foreach ($payments as $payment) {
            if ($remainingAmount <= 0) {
                break;
            }

            $refundableAmount = new Number((string) $payment->amount) - new Number((string) ($payment->refunded_amount ?? '0'));

            if ($refundableAmount <= 0) {
                continue;
            }

            $refundAmount = $refundableAmount < $remainingAmount ? $refundableAmount : $remainingAmount;

            $this->cashService->devolverPago(
                $payment,
                $user,
                (string) $refundAmount->round(2),
                'Devolución de saldo a favor al salir',
                (string) Str::uuid(),
                $cashSession,
            );

            $remainingAmount -= $refundAmount;
        }

        if ($remainingAmount > 0) {
            throw BusinessRuleException::saldoAFavorNoReembolsable($remainingAmount->round(2));
        }

        return $creditAmount->round(2);
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarHabitacionAsignable(Room $room, int $guestCount): void
    {
        if (! $room->estaDisponibleParaAsignar()) {
            throw BusinessRuleException::habitacionNoDisponible();
        }

        if ($room->activeStay()->exists()) {
            throw BusinessRuleException::habitacionOcupada();
        }

        if ($guestCount > $room->capacity) {
            throw BusinessRuleException::capacidadExcedida($room->capacity);
        }
    }

    /**
     * @throws BusinessRuleException
     */
    private function bloquearEstadiaActiva(Stay $stay): Stay
    {
        $lockedStay = Stay::query()->with('branch')->lockForUpdate()->findOrFail($stay->id);

        if ($lockedStay->status !== StayStatus::Active) {
            throw BusinessRuleException::estadiaNoActiva();
        }

        return $lockedStay;
    }

    /**
     * Lock several rooms in id order so concurrent room changes cannot deadlock.
     *
     * @param  list<int>  $roomIds
     * @return Collection<int, Room>
     */
    private function bloquearHabitaciones(array $roomIds): Collection
    {
        return Room::query()
            ->whereKey($roomIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }
}
