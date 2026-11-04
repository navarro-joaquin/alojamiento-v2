<?php

namespace App\Services;

use App\Enums\FixedAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fixed assets of the branches. Every change of branch, room or status is
 * recorded as an asset movement, starting with the registration; retiring
 * an asset is final and needs a reason. Only an asset in use sits in a room,
 * always of its own branch.
 *
 * The asset row is locked while it moves. Authorization and branch checks
 * are the caller's responsibility.
 */
class FixedAssetService
{
    public function __construct(private AuditService $auditService) {}

    /**
     * @param  array{asset_code: string, name: string, serial_number?: ?string, purchase_date?: ?string, purchase_cost?: ?string}  $data
     *
     * @throws BusinessRuleException
     */
    public function registrar(Branch $branch, User $user, array $data, FixedAssetStatus $status, ?Room $room = null): FixedAsset
    {
        if ($status === FixedAssetStatus::Retired) {
            throw BusinessRuleException::bajaSoloConSuOperacion();
        }

        $this->verificarUbicacion($branch, $status, $room);

        return DB::transaction(function () use ($branch, $user, $data, $status, $room): FixedAsset {
            $fixedAsset = FixedAsset::create([
                ...$data,
                'branch_id' => $branch->id,
                'room_id' => $room?->id,
                'status' => $status,
            ]);

            $fixedAsset->movements()->create([
                'to_branch_id' => $branch->id,
                'to_room_id' => $room?->id,
                'to_status' => $status,
                'performed_by' => $user->id,
                'moved_at' => now(),
                'notes' => 'Alta del activo',
            ]);

            $this->auditService->registrar('assets', 'fixed_asset.registered', $fixedAsset, $user, $branch->id, [
                'asset_code' => $fixedAsset->asset_code,
                'room_id' => $room?->id,
                'status' => $status->value,
            ]);

            return $fixedAsset;
        });
    }

    /**
     * Move the asset to a room or branch, or change its status (in use, in
     * storage, in repair). Moving it to another branch hands it over: from
     * then on that branch manages it.
     *
     * @throws BusinessRuleException
     */
    public function mover(FixedAsset $fixedAsset, User $user, Branch $toBranch, FixedAssetStatus $toStatus, ?Room $toRoom = null, ?string $notes = null): FixedAsset
    {
        if ($toStatus === FixedAssetStatus::Retired) {
            throw BusinessRuleException::bajaSoloConSuOperacion();
        }

        if (! $toBranch->is_active) {
            throw BusinessRuleException::sucursalInactiva();
        }

        $this->verificarUbicacion($toBranch, $toStatus, $toRoom);

        return DB::transaction(function () use ($fixedAsset, $user, $toBranch, $toStatus, $toRoom, $notes): FixedAsset {
            $lockedAsset = $this->bloquearActivoVigente($fixedAsset);

            if ($lockedAsset->branch_id === $toBranch->id && $lockedAsset->room_id === $toRoom?->id && $lockedAsset->status === $toStatus) {
                throw BusinessRuleException::movimientoDeActivoSinCambios();
            }

            return $this->registrarMovimiento($lockedAsset, $user, $toBranch, $toRoom, $toStatus, $notes, 'fixed_asset.moved');
        });
    }

    /**
     * @throws BusinessRuleException
     */
    public function darDeBaja(FixedAsset $fixedAsset, User $user, string $reason): FixedAsset
    {
        return DB::transaction(function () use ($fixedAsset, $user, $reason): FixedAsset {
            $lockedAsset = $this->bloquearActivoVigente($fixedAsset);

            return $this->registrarMovimiento($lockedAsset, $user, $lockedAsset->branch, null, FixedAssetStatus::Retired, $reason, 'fixed_asset.retired');
        });
    }

    private function registrarMovimiento(
        FixedAsset $lockedAsset,
        User $user,
        Branch $toBranch,
        ?Room $toRoom,
        FixedAssetStatus $toStatus,
        ?string $notes,
        string $event,
    ): FixedAsset {
        $movement = $lockedAsset->movements()->create([
            'from_branch_id' => $lockedAsset->branch_id,
            'to_branch_id' => $toBranch->id,
            'from_room_id' => $lockedAsset->room_id,
            'to_room_id' => $toRoom?->id,
            'from_status' => $lockedAsset->status,
            'to_status' => $toStatus,
            'performed_by' => $user->id,
            'moved_at' => now(),
            'notes' => $notes,
        ]);

        $lockedAsset->update([
            'branch_id' => $toBranch->id,
            'room_id' => $toRoom?->id,
            'status' => $toStatus,
        ]);

        // Logged in the branch the asset leaves, which is where the operation was made.
        $this->auditService->registrar('assets', $event, $lockedAsset, $user, $movement->from_branch_id, [
            'from_branch_id' => $movement->from_branch_id,
            'to_branch_id' => $movement->to_branch_id,
            'from_room_id' => $movement->from_room_id,
            'to_room_id' => $movement->to_room_id,
            'from_status' => $movement->from_status->value,
            'to_status' => $toStatus->value,
            'notes' => $notes,
        ]);

        return $lockedAsset;
    }

    /**
     * @throws BusinessRuleException
     */
    private function bloquearActivoVigente(FixedAsset $fixedAsset): FixedAsset
    {
        $lockedAsset = FixedAsset::query()->with('branch')->lockForUpdate()->findOrFail($fixedAsset->id);

        if ($lockedAsset->status === FixedAssetStatus::Retired) {
            throw BusinessRuleException::activoDadoDeBaja();
        }

        return $lockedAsset;
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarUbicacion(Branch $branch, FixedAssetStatus $status, ?Room $room): void
    {
        if ($room === null) {
            return;
        }

        if ($status !== FixedAssetStatus::InUse) {
            throw BusinessRuleException::habitacionSoloParaActivoEnUso();
        }

        if ($room->branch_id !== $branch->id) {
            throw BusinessRuleException::habitacionDeOtraSucursal();
        }
    }
}
