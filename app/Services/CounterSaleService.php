<?php

namespace App\Services;

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use App\Enums\FolioType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use BcMath\Number;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sales to people who are not staying: a counter folio is opened, the
 * products leave the warehouse and the total is collected at once. A sale is
 * idempotent on its operation UUID and is voided as a whole.
 *
 * Rows are locked in the canonical order: cash_sessions → folios → payments
 * → inventory_balances (by product). Authorization is the caller's
 * responsibility.
 */
class CounterSaleService
{
    public function __construct(
        private CashService $cashService,
        private ChargeService $chargeService,
        private InventoryService $inventoryService,
        private AuditService $auditService,
    ) {}

    /**
     * @param  array<int, string>  $quantitiesByProduct  quantity by product id
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function vender(
        Warehouse $warehouse,
        CashSession $cashSession,
        User $user,
        array $quantitiesByProduct,
        PaymentMethod $paymentMethod,
        string $operationUuid,
        ?string $holderName = null,
        ?string $reference = null,
    ): Folio {
        if ($quantitiesByProduct === []) {
            throw BusinessRuleException::ventaSinProductos();
        }

        ksort($quantitiesByProduct);
        $quantitiesByProduct = array_map(fn (string $quantity): string => (string) (new Number($quantity))->round(2), $quantitiesByProduct);

        $requestHash = hash('sha256', json_encode([
            'warehouse_id' => $warehouse->id,
            'cash_session_id' => $cashSession->id,
            'items' => $quantitiesByProduct,
            'payment_method' => $paymentMethod->value,
            'holder_name' => $holderName,
        ], JSON_THROW_ON_ERROR));

        $existingSale = Folio::query()->where('operation_uuid', $operationUuid)->first();

        if ($existingSale !== null) {
            return $this->resolverReintento($existingSale, $requestHash, $operationUuid);
        }

        try {
            return DB::transaction(fn (): Folio => $this->registrarVenta(
                $warehouse, $cashSession, $user, $quantitiesByProduct, $paymentMethod, $operationUuid, $requestHash, $holderName, $reference,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentSale = Folio::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentSale, $requestHash, $operationUuid);
        }
    }

    /**
     * Void a whole counter sale: its stock goes back to the warehouse and its
     * payments are refunded through their own methods. Cash refunds leave the
     * register and therefore need an open session of the branch.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function anular(Folio $counterSale, User $user, string $reason, ?CashSession $cashSession = null): Folio
    {
        return DB::transaction(function () use ($counterSale, $user, $reason, $cashSession): Folio {
            if ($cashSession !== null) {
                $this->cashService->bloquearSesionAbierta($cashSession);
            }

            $lockedSale = $this->cashService->bloquearFolio($counterSale);

            if ($lockedSale->folio_type !== FolioType::Counter) {
                throw BusinessRuleException::noEsVentaDeMostrador();
            }

            if ($lockedSale->estaAnulada()) {
                throw BusinessRuleException::ventaDeMostradorYaAnulada();
            }

            $payments = $lockedSale->payments()
                ->confirmados()
                ->withSum(['refunds as refunded_amount' => fn ($query) => $query->confirmadas()], 'amount')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lockedSale->charges()->confirmados()->orderBy('id')->get() as $stayCharge) {
                $this->chargeService->anularCargoBloqueado($stayCharge, $user, $reason, $lockedSale->branch_id);
            }

            foreach ($payments as $payment) {
                $refundableAmount = (new Number((string) $payment->amount) - new Number((string) ($payment->refunded_amount ?? '0')))->round(2);

                if ($refundableAmount > 0) {
                    $this->cashService->devolverPago($payment, $user, (string) $refundableAmount, $reason, (string) Str::uuid(), $cashSession);
                }
            }

            $lockedSale->update([
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->auditService->registrar('cash', 'counter_sale.voided', $lockedSale, $user, $lockedSale->branch_id, [
                'reason' => $reason,
            ]);

            return $lockedSale;
        });
    }

    /**
     * @param  array<int, string>  $quantitiesByProduct
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    private function registrarVenta(
        Warehouse $warehouse,
        CashSession $cashSession,
        User $user,
        array $quantitiesByProduct,
        PaymentMethod $paymentMethod,
        string $operationUuid,
        string $requestHash,
        ?string $holderName,
        ?string $reference,
    ): Folio {
        $lockedSession = $this->cashService->bloquearSesionAbierta($cashSession);

        $concurrentSale = Folio::query()->where('operation_uuid', $operationUuid)->first();

        if ($concurrentSale !== null) {
            return $this->resolverReintento($concurrentSale, $requestHash, $operationUuid);
        }

        if ($warehouse->branch_id !== $lockedSession->cashRegister->branch_id) {
            throw BusinessRuleException::sesionDeOtraSucursal();
        }

        $counterSale = Folio::create([
            'branch_id' => $warehouse->branch_id,
            'folio_type' => FolioType::Counter,
            'holder_name' => $holderName,
            'created_by' => $user->id,
            'operation_uuid' => $operationUuid,
            'request_hash' => $requestHash,
        ]);

        $businessDate = $lockedSession->cashRegister->branch->current_business_date;
        $products = Product::query()->findMany(array_keys($quantitiesByProduct))->keyBy('id');
        $totalAmount = new Number('0.00');

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $product = $products[$productId];
            $quantity = new Number($quantity);

            if (! $product->is_sellable || $product->sale_price === null) {
                throw BusinessRuleException::productoNoVendible();
            }

            if ($quantity <= 0) {
                throw BusinessRuleException::cantidadNoPositiva();
            }

            $lineAmount = ($quantity * new Number($product->sale_price))->round(2);
            $saleMovement = $this->inventoryService->registrarVenta($warehouse, $product, $user, $quantity, "Venta de mostrador #{$counterSale->id}");

            $counterSale->charges()->create([
                'charge_type' => ChargeType::Product,
                'direction' => ChargeDirection::Debit,
                'description' => $product->name,
                'service_date' => $businessDate,
                'quantity' => (string) $quantity,
                'unit_price' => $product->sale_price,
                'total_amount' => (string) $lineAmount,
                'product_id' => $product->id,
                'inventory_movement_id' => $saleMovement->id,
                'status' => ChargeStatus::Confirmed,
                'created_by' => $user->id,
            ]);

            $totalAmount += $lineAmount;
        }

        $this->cashService->cobrarPago($counterSale, $lockedSession, $user, $paymentMethod, PaymentType::Settlement, (string) $totalAmount, $operationUuid, $reference);

        $this->auditService->registrar('cash', 'counter_sale.sold', $counterSale, $user, $counterSale->branch_id, [
            'warehouse_id' => $warehouse->id,
            'items' => $quantitiesByProduct,
            'total_amount' => (string) $totalAmount,
            'payment_method' => $paymentMethod->value,
        ]);

        return $counterSale;
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function resolverReintento(Folio $existingSale, string $requestHash, string $operationUuid): Folio
    {
        if (! hash_equals((string) $existingSale->request_hash, $requestHash)) {
            throw IdempotencyConflictException::paraOperacion($operationUuid);
        }

        return $existingSale;
    }
}
