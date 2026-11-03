<?php

namespace App\Services;

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use BcMath\Number;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Charges, discounts and voids on a stay. Amounts are always positive; the
 * direction gives the sign. Authorization for discounts and voids is the
 * caller's responsibility.
 */
class ChargeService
{
    /**
     * Manually chargeable types. Lodging nights come from check-in and the
     * night audit; discounts have their own operation.
     *
     * @var list<ChargeType>
     */
    public const array MANUAL_DEBIT_TYPES = [
        ChargeType::LateCheckOut,
        ChargeType::Product,
        ChargeType::Service,
        ChargeType::Adjustment,
    ];

    public function __construct(private AuditService $auditService) {}

    /**
     * Charge the night of a business date at the stay's agreed rate. Idempotent:
     * if the night is already charged, the existing charge is returned
     * (`wasRecentlyCreated` tells both cases apart).
     */
    public function registrarNocheAlojamiento(Stay $stay, User $user, CarbonInterface $serviceDate): StayCharge
    {
        $existingCharge = $this->buscarNocheCobrada($stay, $serviceDate);

        if ($existingCharge !== null) {
            return $existingCharge;
        }

        try {
            return DB::transaction(fn (): StayCharge => $stay->charges()->create([
                'charge_type' => ChargeType::Lodging,
                'direction' => ChargeDirection::Debit,
                'description' => 'Noche del '.$serviceDate->format('d/m/Y'),
                'service_date' => $serviceDate->toDateString(),
                'quantity' => '1.00',
                'unit_price' => $stay->rate_amount,
                'total_amount' => $stay->rate_amount,
                'status' => ChargeStatus::Confirmed,
                'created_by' => $user->id,
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->buscarNocheCobrada($stay, $serviceDate) ?? throw $exception;
        }
    }

    /**
     * @throws BusinessRuleException
     */
    public function registrarCargo(Stay $stay, User $user, ChargeType $chargeType, string $description, string $quantity, string $unitPrice): StayCharge
    {
        if (! in_array($chargeType, self::MANUAL_DEBIT_TYPES, true)) {
            throw BusinessRuleException::tipoDeCargoNoPermitido();
        }

        $quantity = (new Number($quantity))->round(2);
        $unitPrice = (new Number($unitPrice))->round(2);

        if ($quantity <= 0) {
            throw BusinessRuleException::importeNoPositivo();
        }

        if ($unitPrice < 0) {
            throw BusinessRuleException::importeNegativo();
        }

        return $this->crearCargo($stay, $user, 'stay_charge.added', [
            'charge_type' => $chargeType,
            'direction' => ChargeDirection::Debit,
            'description' => $description,
            'quantity' => (string) $quantity,
            'unit_price' => (string) $unitPrice,
            'total_amount' => (string) ($quantity * $unitPrice)->round(2),
        ]);
    }

    /**
     * @throws BusinessRuleException
     */
    public function registrarDescuento(Stay $stay, User $user, string $description, string $amount): StayCharge
    {
        $amount = (new Number($amount))->round(2);

        if ($amount <= 0) {
            throw BusinessRuleException::importeNoPositivo();
        }

        return $this->crearCargo($stay, $user, 'stay_charge.discounted', [
            'charge_type' => ChargeType::Discount,
            'direction' => ChargeDirection::Credit,
            'description' => $description,
            'quantity' => '1.00',
            'unit_price' => (string) $amount,
            'total_amount' => (string) $amount,
        ]);
    }

    /**
     * Void a charge without deleting it. A voided lodging night may be charged again.
     *
     * @throws BusinessRuleException
     */
    public function anularCargo(StayCharge $stayCharge, User $user, string $reason): StayCharge
    {
        return DB::transaction(function () use ($stayCharge, $user, $reason): StayCharge {
            $lockedStay = $this->bloquearEstadiaActiva($stayCharge->stay);
            $lockedCharge = StayCharge::query()->lockForUpdate()->findOrFail($stayCharge->id);

            if ($lockedCharge->status !== ChargeStatus::Confirmed) {
                throw BusinessRuleException::cargoNoConfirmado();
            }

            $lockedCharge->update([
                'status' => ChargeStatus::Voided,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->auditService->registrar('reception', 'stay_charge.voided', $lockedCharge, $user, $lockedStay->branch_id, [
                'stay_id' => $lockedStay->id,
                'total_amount' => $lockedCharge->total_amount,
                'reason' => $reason,
            ]);

            return $lockedCharge;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws BusinessRuleException
     */
    private function crearCargo(Stay $stay, User $user, string $event, array $attributes): StayCharge
    {
        return DB::transaction(function () use ($stay, $user, $event, $attributes): StayCharge {
            $lockedStay = $this->bloquearEstadiaActiva($stay);

            $stayCharge = $lockedStay->charges()->create([
                ...$attributes,
                'service_date' => $lockedStay->branch->current_business_date,
                'status' => ChargeStatus::Confirmed,
                'created_by' => $user->id,
            ]);

            $this->auditService->registrar('reception', $event, $stayCharge, $user, $lockedStay->branch_id, [
                'stay_id' => $lockedStay->id,
                'charge_type' => $stayCharge->charge_type->value,
                'total_amount' => $stayCharge->total_amount,
            ]);

            return $stayCharge;
        });
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

    private function buscarNocheCobrada(Stay $stay, CarbonInterface $serviceDate): ?StayCharge
    {
        return $stay->charges()
            ->confirmados()
            ->where('charge_type', ChargeType::Lodging)
            ->whereDate('service_date', $serviceDate)
            ->first();
    }
}
