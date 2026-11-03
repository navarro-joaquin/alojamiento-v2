<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes a branch business date: charges that night to every active stay and
 * moves the branch to the next business date.
 *
 * Stays charge their first night at check-in, so a stay that checked in on the
 * closing date is skipped by the idempotent night charge.
 */
class NightAuditService
{
    public function __construct(
        private ChargeService $chargeService,
        private AuditService $auditService,
    ) {}

    /**
     * A business date can be closed once the branch local time reaches the
     * cut-off hour of the following day (e.g. 04:00), so late-night arrivals
     * still belong to the previous night. Running twice is therefore harmless.
     */
    public function puedeCerrar(Branch $branch): bool
    {
        $closingMoment = $branch->current_business_date
            ->toImmutable()
            ->shiftTimezone($branch->timezone)
            ->addDay()
            ->setTime((int) config('lodging.night_audit_hour'), 0);

        return now($branch->timezone)->greaterThanOrEqualTo($closingMoment);
    }

    /**
     * @return int Number of nights charged.
     *
     * @throws BusinessRuleException
     */
    public function cerrarFechaDeNegocio(Branch $branch, User $user): int
    {
        return DB::transaction(function () use ($branch, $user): int {
            $lockedBranch = Branch::query()->lockForUpdate()->findOrFail($branch->id);

            if (! $this->puedeCerrar($lockedBranch)) {
                throw BusinessRuleException::cierreDiarioNoCorresponde();
            }

            $businessDate = $lockedBranch->current_business_date->toImmutable();

            $activeStays = Stay::query()
                ->activas()
                ->whereBelongsTo($lockedBranch)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $chargedNights = $activeStays
                ->map(fn (Stay $stay) => $this->chargeService->registrarNocheAlojamiento($stay, $user, $businessDate))
                ->filter(fn ($stayCharge): bool => $stayCharge->wasRecentlyCreated)
                ->count();

            $lockedBranch->update(['current_business_date' => $businessDate->addDay()]);

            $this->auditService->registrar('reception', 'business_date.closed', $lockedBranch, $user, $lockedBranch->id, [
                'business_date' => $businessDate->toDateString(),
                'active_stays' => $activeStays->count(),
                'charged_nights' => $chargedNights,
            ]);

            return $chargedNights;
        });
    }
}
