<?php

namespace App\Services;

use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use BcMath\Number;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Stock of each product per warehouse. Every movement updates the balance in
 * the same transaction, with the balance row locked, and no movement leaves
 * the stock negative. Stock is valued at weighted average cost: entries
 * recompute it, exits leave at it.
 *
 * Rows are locked in the canonical order: inventory_balances last, after any
 * stay or cash row the caller locked. Manual movements are idempotent on
 * their operation UUID. Authorization and branch checks are the caller's
 * responsibility.
 */
class InventoryService
{
    public function __construct(private AuditService $auditService) {}

    /**
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function registrarEntrada(
        Warehouse $warehouse,
        Product $product,
        User $user,
        string $quantity,
        string $unitCost,
        string $operationUuid,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        $this->verificarActivos($warehouse, $product);
        $quantity = $this->cantidadPositiva($quantity);
        $unitCost = (new Number($unitCost))->round(4);

        if ($unitCost < 0) {
            throw BusinessRuleException::costoNegativo();
        }

        return $this->registrarUnaVez($operationUuid, [
            'movement_type' => InventoryMovementType::Entry->value,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => (string) $quantity,
            'unit_cost' => (string) $unitCost,
        ], fn (string $requestHash): InventoryMovement => $this->registrarMovimiento(
            $this->bloquearSaldo($warehouse, $product), $warehouse, $product, $user,
            InventoryMovementType::Entry, InventoryMovementDirection::In, $quantity, $unitCost,
            ['reference' => $reference, 'notes' => $notes, 'operation_uuid' => $operationUuid, 'request_hash' => $requestHash],
        ));
    }

    /**
     * Manual exit: an exit (loss, breakage, return to supplier) or an internal consumption.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function registrarSalida(
        Warehouse $warehouse,
        Product $product,
        User $user,
        InventoryMovementType $movementType,
        string $quantity,
        string $operationUuid,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        if (! in_array($movementType, InventoryMovementType::salidasManuales(), true)) {
            throw BusinessRuleException::tipoDeSalidaNoPermitido();
        }

        $this->verificarActivos($warehouse, $product);
        $quantity = $this->cantidadPositiva($quantity);

        return $this->registrarUnaVez($operationUuid, [
            'movement_type' => $movementType->value,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => (string) $quantity,
        ], fn (string $requestHash): InventoryMovement => $this->registrarMovimiento(
            $this->bloquearSaldo($warehouse, $product), $warehouse, $product, $user,
            $movementType, InventoryMovementDirection::Out, $quantity, null,
            ['reference' => $reference, 'notes' => $notes, 'operation_uuid' => $operationUuid, 'request_hash' => $requestHash],
        ));
    }

    /**
     * Bring the stock to a physical count. The difference is computed with the
     * balance locked, so a movement registered meanwhile is not overwritten.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function registrarAjuste(
        Warehouse $warehouse,
        Product $product,
        User $user,
        string $countedQuantity,
        string $notes,
        string $operationUuid,
    ): InventoryMovement {
        $this->verificarActivos($warehouse, $product);
        $countedQuantity = (new Number($countedQuantity))->round(3);

        if ($countedQuantity < 0) {
            throw BusinessRuleException::conteoNegativo();
        }

        return $this->registrarUnaVez($operationUuid, [
            'movement_type' => InventoryMovementType::Adjustment->value,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'counted_quantity' => (string) $countedQuantity,
        ], function (string $requestHash) use ($warehouse, $product, $user, $countedQuantity, $notes, $operationUuid): InventoryMovement {
            $lockedBalance = $this->bloquearSaldo($warehouse, $product);
            $currentQuantity = new Number($lockedBalance->quantity);

            if ($countedQuantity == $currentQuantity) {
                throw BusinessRuleException::ajusteSinDiferencia();
            }

            $isSurplus = $countedQuantity > $currentQuantity;

            return $this->registrarMovimiento(
                $lockedBalance, $warehouse, $product, $user,
                InventoryMovementType::Adjustment,
                $isSurplus ? InventoryMovementDirection::In : InventoryMovementDirection::Out,
                $isSurplus ? $countedQuantity - $currentQuantity : $currentQuantity - $countedQuantity,
                null,
                ['notes' => $notes, 'operation_uuid' => $operationUuid, 'request_hash' => $requestHash],
            );
        });
    }

    /**
     * Take a product sold to a guest out of stock, at the average cost. Meant
     * to run inside the transaction of the charge, after the stay was locked.
     *
     * @throws BusinessRuleException
     */
    public function registrarVenta(Warehouse $warehouse, Product $product, User $user, Number $quantity, string $reference): InventoryMovement
    {
        $this->verificarActivos($warehouse, $product);

        return DB::transaction(fn (): InventoryMovement => $this->registrarMovimiento(
            $this->bloquearSaldo($warehouse, $product), $warehouse, $product, $user,
            InventoryMovementType::Sale, InventoryMovementDirection::Out, $quantity, null,
            ['reference' => $reference],
        ));
    }

    /**
     * Put the stock of a voided sale back, at the cost it left with. It is
     * accepted even if the product or the warehouse was deactivated since.
     */
    public function registrarDevolucionDeVenta(InventoryMovement $saleMovement, User $user, string $reference): InventoryMovement
    {
        $saleMovement->loadMissing(['warehouse', 'product']);

        return DB::transaction(fn (): InventoryMovement => $this->registrarMovimiento(
            $this->bloquearSaldo($saleMovement->warehouse, $saleMovement->product), $saleMovement->warehouse, $saleMovement->product, $user,
            InventoryMovementType::SaleReturn, InventoryMovementDirection::In,
            new Number($saleMovement->quantity), new Number($saleMovement->unit_cost),
            ['reference' => $reference],
        ));
    }

    /**
     * Movement of a transfer: transfer_out on sending, transfer_in on
     * reception and an outgoing adjustment for a shortage found on reception.
     * Meant for InventoryTransferService, which locks the transfer first and
     * goes through its products in id order.
     *
     * @throws BusinessRuleException
     */
    public function registrarMovimientoDeTransferencia(
        InventoryTransfer $inventoryTransfer,
        Warehouse $warehouse,
        Product $product,
        User $user,
        InventoryMovementType $movementType,
        Number $quantity,
        ?Number $unitCost = null,
        ?string $notes = null,
    ): InventoryMovement {
        $direction = match ($movementType) {
            InventoryMovementType::TransferOut, InventoryMovementType::Adjustment => InventoryMovementDirection::Out,
            InventoryMovementType::TransferIn => InventoryMovementDirection::In,
            default => throw new LogicException("A transfer does not register {$movementType->value} movements."),
        };

        return DB::transaction(fn (): InventoryMovement => $this->registrarMovimiento(
            $this->bloquearSaldo($warehouse, $product), $warehouse, $product, $user,
            $movementType, $direction, $quantity, $unitCost,
            ['inventory_transfer_id' => $inventoryTransfer->id, 'reference' => "Transferencia #{$inventoryTransfer->id}", 'notes' => $notes],
        ));
    }

    /**
     * Lock the balance of the product in the warehouse, creating it empty the
     * first time. The lock is taken before inserting so concurrent movements
     * of an existing balance always wait on it.
     */
    private function bloquearSaldo(Warehouse $warehouse, Product $product): InventoryBalance
    {
        $lockedBalance = fn (): ?InventoryBalance => InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($balance = $lockedBalance()) {
            return $balance;
        }

        InventoryBalance::query()->insertOrIgnore([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'average_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $lockedBalance() ?? throw new LogicException('The inventory balance could not be created.');
    }

    /**
     * Apply a movement to a locked balance. Entries with a cost recompute the
     * weighted average cost; exits and adjustments move at the current one.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws BusinessRuleException
     */
    private function registrarMovimiento(
        InventoryBalance $lockedBalance,
        Warehouse $warehouse,
        Product $product,
        User $user,
        InventoryMovementType $movementType,
        InventoryMovementDirection $direction,
        Number $quantity,
        ?Number $unitCost,
        array $attributes,
    ): InventoryMovement {
        $currentQuantity = new Number($lockedBalance->quantity);
        $averageCost = new Number($lockedBalance->average_cost);
        $unitCost ??= $averageCost;

        if ($direction === InventoryMovementDirection::In) {
            $balanceAfter = $currentQuantity + $quantity;
            // div() truncates at the given scale: divide with spare digits and round when storing.
            $averageCost = ($currentQuantity * $averageCost + $quantity * $unitCost)->div($balanceAfter, 10);
        } else {
            if ($quantity > $currentQuantity) {
                throw BusinessRuleException::existenciasInsuficientes($currentQuantity, $product->unit);
            }

            $balanceAfter = $currentQuantity - $quantity;
        }

        $lockedBalance->update([
            'quantity' => (string) $balanceAfter->round(3),
            'average_cost' => (string) $averageCost->round(4),
        ]);

        $movement = InventoryMovement::create([
            ...$attributes,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'performed_by' => $user->id,
            'movement_type' => $movementType,
            'direction' => $direction,
            'quantity' => (string) $quantity,
            'unit_cost' => (string) $unitCost->round(4),
            'balance_after' => (string) $balanceAfter->round(3),
        ]);

        $this->auditService->registrar('inventory', "inventory.{$movementType->value}", $movement, $user, $warehouse->branch_id, [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'direction' => $direction->value,
            'quantity' => $movement->quantity,
            'unit_cost' => $movement->unit_cost,
            'balance_after' => $movement->balance_after,
        ]);

        return $movement;
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @param  Closure(string): InventoryMovement  $operation  Receives the request hash; runs inside the transaction.
     *
     * @throws IdempotencyConflictException
     */
    private function registrarUnaVez(string $operationUuid, array $requestData, Closure $operation): InventoryMovement
    {
        $requestHash = hash('sha256', json_encode($requestData, JSON_THROW_ON_ERROR));
        $existingMovement = InventoryMovement::query()->where('operation_uuid', $operationUuid)->first();

        if ($existingMovement !== null) {
            return $this->resolverReintento($existingMovement, $requestHash, $operationUuid);
        }

        try {
            return DB::transaction(fn (): InventoryMovement => $operation($requestHash));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentMovement = InventoryMovement::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentMovement, $requestHash, $operationUuid);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function resolverReintento(InventoryMovement $existingMovement, string $requestHash, string $operationUuid): InventoryMovement
    {
        if (! hash_equals($existingMovement->request_hash, $requestHash)) {
            throw IdempotencyConflictException::paraOperacion($operationUuid);
        }

        return $existingMovement;
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarActivos(Warehouse $warehouse, Product $product): void
    {
        if (! $warehouse->is_active) {
            throw BusinessRuleException::almacenInactivo();
        }

        if (! $product->is_active) {
            throw BusinessRuleException::productoInactivo();
        }
    }

    /**
     * @throws BusinessRuleException
     */
    private function cantidadPositiva(string $quantity): Number
    {
        $quantity = (new Number($quantity))->round(3);

        if ($quantity <= 0) {
            throw BusinessRuleException::cantidadNoPositiva();
        }

        return $quantity;
    }
}
