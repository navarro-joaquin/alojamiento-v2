<?php

use App\Enums\CashMovementDirection;
use App\Enums\ChargeStatus;
use App\Enums\FolioType;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashService;
use App\Services\ChargeService;
use App\Services\CounterSaleService;
use App\Services\FolioBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create(['opening_amount' => '100.00']);
    $this->store = Warehouse::factory()->for($this->branch)->create(['name' => 'Tienda']);
    $this->soda = Product::factory()->vendible('12.50')->create(['name' => 'Gaseosa 500 ml']);
    $this->water = Product::factory()->vendible('8.00')->create(['name' => 'Agua 600 ml']);
    InventoryBalance::factory()->for($this->store)->for($this->soda)->create(['quantity' => '10.000', 'average_cost' => '6.0000']);
    InventoryBalance::factory()->for($this->store)->for($this->water)->create(['quantity' => '5.000', 'average_cost' => '3.0000']);
    $this->clerk = User::factory()->create();
});

/**
 * @param  array<int, string>  $quantitiesByProduct
 */
function venderEnMostrador(array $quantitiesByProduct, ?string $operationUuid = null, PaymentMethod $paymentMethod = PaymentMethod::Cash, ?string $holderName = 'Juan Pérez'): Folio
{
    $test = test();

    return app(CounterSaleService::class)->vender(
        $test->store, $test->cashSession, $test->clerk, $quantitiesByProduct, $paymentMethod, $operationUuid ?? (string) Str::uuid(), $holderName,
    );
}

function existenciasEnTienda(Product $product): string
{
    return InventoryBalance::query()->where('warehouse_id', test()->store->id)->whereBelongsTo($product)->value('quantity');
}

describe('vender', function () {
    it('takes the products out of stock and collects the total at once', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '2', $this->water->id => '1']);

        expect($counterSale->only(['folio_type', 'stay_id', 'holder_name', 'branch_id']))->toBe([
            'folio_type' => FolioType::Counter,
            'stay_id' => null,
            'holder_name' => 'Juan Pérez',
            'branch_id' => $this->branch->id,
        ])
            ->and($counterSale->charges()->pluck('total_amount', 'description')->all())->toBe(['Gaseosa 500 ml' => '25.00', 'Agua 600 ml' => '8.00'])
            ->and($counterSale->charges()->value('service_date')->toDateString())->toBe('2026-11-03')
            ->and(existenciasEnTienda($this->soda))->toBe('8.000')
            ->and(existenciasEnTienda($this->water))->toBe('4.000')
            ->and((string) app(FolioBalanceService::class)->calcular($counterSale))->toBe('0.00');

        $payment = $counterSale->payments()->sole();
        expect($payment->only(['amount', 'payment_type', 'payment_method']))->toBe(['amount' => '33.00', 'payment_type' => PaymentType::Settlement, 'payment_method' => PaymentMethod::Cash])
            ->and($payment->cashMovements()->sole()->only(['direction', 'amount', 'description']))->toBe([
                'direction' => CashMovementDirection::In,
                'amount' => '33.00',
                'description' => "Cobro de la Venta de mostrador #{$counterSale->id}",
            ])
            ->and((string) app(CashService::class)->calcularEfectivoEsperado($this->cashSession))->toBe('133.00');

        $this->assertDatabaseHas('activity_log', ['event' => 'counter_sale.sold', 'subject_id' => $counterSale->id]);
    });

    it('returns the same sale when the form is submitted twice', function () {
        $operationUuid = (string) Str::uuid();

        $firstSale = venderEnMostrador([$this->soda->id => '2'], $operationUuid);
        $retriedSale = venderEnMostrador([$this->soda->id => '2'], $operationUuid);

        expect($retriedSale->is($firstSale))->toBeTrue()
            ->and(Folio::query()->where('folio_type', FolioType::Counter)->count())->toBe(1)
            ->and(existenciasEnTienda($this->soda))->toBe('8.000');
    });

    it('rejects reusing the operation for a different sale', function () {
        $operationUuid = (string) Str::uuid();
        venderEnMostrador([$this->soda->id => '2'], $operationUuid);

        venderEnMostrador([$this->soda->id => '3'], $operationUuid);
    })->throws(IdempotencyConflictException::class);

    it('sells nothing when a product lacks stock', function () {
        expect(fn () => venderEnMostrador([$this->soda->id => '2', $this->water->id => '6']))->toThrow(BusinessRuleException::class);

        expect(Folio::query()->where('folio_type', FolioType::Counter)->exists())->toBeFalse()
            ->and(existenciasEnTienda($this->soda))->toBe('10.000')
            ->and($this->cashSession->movements()->exists())->toBeFalse();
    });

    it('rejects products that are not for sale', function () {
        $towel = Product::factory()->create(['is_sellable' => false]);
        InventoryBalance::factory()->for($this->store)->for($towel)->create(['quantity' => '5.000']);

        venderEnMostrador([$towel->id => '1']);
    })->throws(BusinessRuleException::class, 'no está a la venta');

    it('rejects a cash register of another branch', function () {
        $this->cashSession = CashSession::factory()->create();

        venderEnMostrador([$this->soda->id => '1']);
    })->throws(BusinessRuleException::class, 'sesión de caja pertenece a otra sucursal');

    it('rejects a sale without products', function () {
        venderEnMostrador([]);
    })->throws(BusinessRuleException::class, 'al menos un producto');

    it('takes no deposits on a counter sale', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '1']);

        app(CashService::class)->cobrarPago($counterSale, $this->cashSession, $this->clerk, PaymentMethod::Cash, PaymentType::Deposit, '10.00', (string) Str::uuid());
    })->throws(BusinessRuleException::class, 'Solo se reciben anticipos de estadías');
});

describe('anular', function () {
    it('returns the stock and refunds the payment from the register', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '2', $this->water->id => '1']);
        $supervisor = User::factory()->create();

        app(CounterSaleService::class)->anular($counterSale, $supervisor, 'Cliente se arrepintió', $this->cashSession);

        $counterSale->refresh();
        expect($counterSale->estaAnulada())->toBeTrue()
            ->and($counterSale->voided_by)->toBe($supervisor->id)
            ->and($counterSale->charges()->pluck('status')->unique()->all())->toBe([ChargeStatus::Voided])
            ->and(existenciasEnTienda($this->soda))->toBe('10.000')
            ->and(existenciasEnTienda($this->water))->toBe('5.000')
            ->and($counterSale->payments()->sole()->refunds()->sole()->amount)->toBe('33.00')
            ->and((string) app(FolioBalanceService::class)->calcular($counterSale))->toBe('0.00')
            ->and((string) app(CashService::class)->calcularEfectivoEsperado($this->cashSession))->toBe('100.00');

        $this->assertDatabaseHas('inventory_movements', ['movement_type' => InventoryMovementType::SaleReturn->value, 'product_id' => $this->soda->id]);
        $this->assertDatabaseHas('activity_log', ['event' => 'counter_sale.voided', 'subject_id' => $counterSale->id]);
    });

    it('refunds a card payment without a register', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '1'], paymentMethod: PaymentMethod::Card);

        app(CounterSaleService::class)->anular($counterSale, User::factory()->create(), 'Cobro equivocado');

        expect($counterSale->payments()->sole()->refunds()->sole()->cash_session_id)->toBeNull();
    });

    it('needs a register to give the cash back', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '1']);

        expect(fn () => app(CounterSaleService::class)->anular($counterSale, User::factory()->create(), 'Error'))
            ->toThrow(BusinessRuleException::class);

        expect($counterSale->fresh()->estaAnulada())->toBeFalse()
            ->and(existenciasEnTienda($this->soda))->toBe('9.000');
    });

    it('voids a sale only once', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '1']);
        app(CounterSaleService::class)->anular($counterSale, User::factory()->create(), 'Error', $this->cashSession);

        app(CounterSaleService::class)->anular($counterSale, User::factory()->create(), 'Otra vez', $this->cashSession);
    })->throws(BusinessRuleException::class, 'ya está anulada');

    it('leaves the charges of a counter sale out of single voids', function () {
        $counterSale = venderEnMostrador([$this->soda->id => '1']);

        app(ChargeService::class)->anularCargo($counterSale->charges()->sole(), User::factory()->create(), 'Error');
    })->throws(BusinessRuleException::class, 'junto con la venta completa');
});
