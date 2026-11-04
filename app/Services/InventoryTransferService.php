<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryTransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use BcMath\Number;
use Illuminate\Support\Facades\DB;

/**
 * Transfers between warehouses, also of different branches. A draft moves
 * nothing; sending takes the stock out of the source at its average cost
 * (transfer_out) and receiving puts it into the destination at that same
 * cost (transfer_in). A shortage found on reception leaves the destination
 * as an adjustment with its reason, so the source keeps what it sent.
 *
 * Rows are locked in the canonical order: inventory_transfers →
 * inventory_balances, by product id. Authorization and branch checks are
 * the caller's responsibility.
 */
class InventoryTransferService
{
    public function __construct(
        private InventoryService $inventoryService,
        private AuditService $auditService,
    ) {}

    /**
     * @param  array<int, string>  $quantitiesByProduct  Product id => quantity to send.
     *
     * @throws BusinessRuleException
     */
    public function crear(Warehouse $fromWarehouse, Warehouse $toWarehouse, User $user, array $quantitiesByProduct, ?string $notes = null): InventoryTransfer
    {
        if ($fromWarehouse->id === $toWarehouse->id) {
            throw BusinessRuleException::transferenciaAlMismoAlmacen();
        }

        if (! $fromWarehouse->is_active || ! $toWarehouse->is_active) {
            throw BusinessRuleException::almacenInactivo();
        }

        if ($quantitiesByProduct === []) {
            throw BusinessRuleException::transferenciaSinProductos();
        }

        $quantities = array_map(function (string $quantity): Number {
            $quantity = (new Number($quantity))->round(3);

            if ($quantity <= 0) {
                throw BusinessRuleException::cantidadNoPositiva();
            }

            return $quantity;
        }, $quantitiesByProduct);

        if (Product::query()->activos()->whereKey(array_keys($quantities))->count() !== count($quantities)) {
            throw BusinessRuleException::productoInactivo();
        }

        return DB::transaction(function () use ($fromWarehouse, $toWarehouse, $user, $quantities, $notes): InventoryTransfer {
            $inventoryTransfer = InventoryTransfer::create([
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => InventoryTransferStatus::Draft,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            foreach ($quantities as $productId => $quantity) {
                $inventoryTransfer->items()->create(['product_id' => $productId, 'quantity' => (string) $quantity]);
            }

            $this->auditService->registrar('inventory', 'inventory_transfer.created', $inventoryTransfer, $user, $fromWarehouse->branch_id, [
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'items' => array_map(fn (Number $quantity): string => (string) $quantity, $quantities),
            ]);

            return $inventoryTransfer;
        });
    }

    /**
     * Take every product out of the source warehouse. If one of them lacks
     * stock, nothing is sent.
     *
     * @throws BusinessRuleException
     */
    public function enviar(InventoryTransfer $inventoryTransfer, User $user): InventoryTransfer
    {
        return DB::transaction(function () use ($inventoryTransfer, $user): InventoryTransfer {
            $lockedTransfer = $this->bloquearTransferencia($inventoryTransfer, InventoryTransferStatus::Draft);

            if (! $lockedTransfer->fromWarehouse->is_active || ! $lockedTransfer->toWarehouse->is_active) {
                throw BusinessRuleException::almacenInactivo();
            }

            foreach ($lockedTransfer->items as $item) {
                if (! $item->product->is_active) {
                    throw BusinessRuleException::productoInactivo();
                }

                $movement = $this->inventoryService->registrarMovimientoDeTransferencia(
                    $lockedTransfer, $lockedTransfer->fromWarehouse, $item->product, $user,
                    InventoryMovementType::TransferOut, new Number($item->quantity),
                );

                $item->update(['unit_cost' => $movement->unit_cost]);
            }

            $lockedTransfer->update([
                'status' => InventoryTransferStatus::Sent,
                'sent_by' => $user->id,
                'sent_at' => now(),
            ]);

            $this->auditService->registrar('inventory', 'inventory_transfer.sent', $lockedTransfer, $user, $lockedTransfer->fromWarehouse->branch_id, [
                'to_warehouse_id' => $lockedTransfer->to_warehouse_id,
            ]);

            return $lockedTransfer;
        });
    }

    /**
     * Put the products into the destination warehouse. A product not listed
     * is received in full; receiving less requires the reason of the shortage.
     *
     * @param  array<int, string>  $receivedByProduct  Product id => quantity received.
     *
     * @throws BusinessRuleException
     */
    public function recibir(InventoryTransfer $inventoryTransfer, User $user, array $receivedByProduct = [], ?string $notes = null): InventoryTransfer
    {
        return DB::transaction(function () use ($inventoryTransfer, $user, $receivedByProduct, $notes): InventoryTransfer {
            $lockedTransfer = $this->bloquearTransferencia($inventoryTransfer, InventoryTransferStatus::Sent);

            $receivedQuantities = $lockedTransfer->items->mapWithKeys(function (InventoryTransferItem $item) use ($receivedByProduct): array {
                $sentQuantity = new Number($item->quantity);
                $receivedQuantity = isset($receivedByProduct[$item->product_id]) ? (new Number($receivedByProduct[$item->product_id]))->round(3) : $sentQuantity;

                if ($receivedQuantity < 0 || $receivedQuantity > $sentQuantity) {
                    throw BusinessRuleException::cantidadRecibidaInvalida();
                }

                return [$item->id => $receivedQuantity];
            });

            $hasShortage = $lockedTransfer->items->contains(fn (InventoryTransferItem $item): bool => $receivedQuantities[$item->id] < new Number($item->quantity));

            if ($hasShortage && blank($notes)) {
                throw BusinessRuleException::faltanteSinMotivo();
            }

            foreach ($lockedTransfer->items as $item) {
                $sentQuantity = new Number($item->quantity);
                $receivedQuantity = $receivedQuantities[$item->id];

                $this->inventoryService->registrarMovimientoDeTransferencia(
                    $lockedTransfer, $lockedTransfer->toWarehouse, $item->product, $user,
                    InventoryMovementType::TransferIn, $sentQuantity, new Number($item->unit_cost),
                );

                if ($receivedQuantity < $sentQuantity) {
                    $this->inventoryService->registrarMovimientoDeTransferencia(
                        $lockedTransfer, $lockedTransfer->toWarehouse, $item->product, $user,
                        InventoryMovementType::Adjustment, $sentQuantity - $receivedQuantity,
                        notes: "Faltante en la recepción: {$notes}",
                    );
                }

                $item->update(['received_quantity' => (string) $receivedQuantity]);
            }

            $lockedTransfer->update([
                'status' => InventoryTransferStatus::Received,
                'received_by' => $user->id,
                'received_at' => now(),
                'reception_notes' => $notes,
            ]);

            $this->auditService->registrar('inventory', 'inventory_transfer.received', $lockedTransfer, $user, $lockedTransfer->toWarehouse->branch_id, [
                'from_warehouse_id' => $lockedTransfer->from_warehouse_id,
                'has_shortage' => $hasShortage,
            ]);

            return $lockedTransfer;
        });
    }

    /**
     * Only a draft is cancelled: once sent, the stock is on its way.
     *
     * @throws BusinessRuleException
     */
    public function cancelar(InventoryTransfer $inventoryTransfer, User $user): InventoryTransfer
    {
        return DB::transaction(function () use ($inventoryTransfer, $user): InventoryTransfer {
            $lockedTransfer = $this->bloquearTransferencia($inventoryTransfer, InventoryTransferStatus::Draft);

            $lockedTransfer->update([
                'status' => InventoryTransferStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            $this->auditService->registrar('inventory', 'inventory_transfer.cancelled', $lockedTransfer, $user, $lockedTransfer->fromWarehouse->branch_id);

            return $lockedTransfer;
        });
    }

    /**
     * Lock the transfer and load its items in product order, the order in
     * which their balances are locked.
     *
     * @throws BusinessRuleException
     */
    private function bloquearTransferencia(InventoryTransfer $inventoryTransfer, InventoryTransferStatus $expectedStatus): InventoryTransfer
    {
        $lockedTransfer = InventoryTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'items' => fn ($query) => $query->with('product')->orderBy('product_id')])
            ->lockForUpdate()
            ->findOrFail($inventoryTransfer->id);

        if ($lockedTransfer->status !== $expectedStatus) {
            throw $expectedStatus === InventoryTransferStatus::Draft
                ? BusinessRuleException::transferenciaNoEnBorrador()
                : BusinessRuleException::transferenciaNoEnTransito();
        }

        return $lockedTransfer;
    }
}
