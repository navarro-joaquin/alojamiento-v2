<?php

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ChargeService;
use App\Services\FolioBalanceService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch))->create();
    $this->minibar = Warehouse::factory()->for($this->branch)->create(['name' => 'Minibar']);
    $this->product = Product::factory()->vendible('12.50')->create(['name' => 'Gaseosa 500 ml', 'unit' => 'botella']);
    $this->balance = InventoryBalance::factory()->for($this->minibar)->for($this->product)->create(['quantity' => '10.000', 'average_cost' => '6.0000']);
    $this->receptionist = User::factory()->create();
});

function venderAlHuesped(string $quantity, ?Warehouse $warehouse = null, ?Product $product = null, ?Stay $stay = null)
{
    $test = test();

    return app(ChargeService::class)->venderProducto($stay ?? $test->stay, $test->receptionist, $warehouse ?? $test->minibar, $product ?? $test->product, $quantity);
}

it('charges the product at its sale price and takes it out of stock', function () {
    $stayCharge = venderAlHuesped('2');

    expect($stayCharge->only(['charge_type', 'direction', 'description', 'quantity', 'unit_price', 'total_amount', 'status']))->toBe([
        'charge_type' => ChargeType::Product,
        'direction' => ChargeDirection::Debit,
        'description' => 'Gaseosa 500 ml',
        'quantity' => '2.00',
        'unit_price' => '12.50',
        'total_amount' => '25.00',
        'status' => ChargeStatus::Confirmed,
    ])
        ->and($stayCharge->service_date->toDateString())->toBe('2026-11-03')
        ->and($stayCharge->product->is($this->product))->toBeTrue()
        ->and($stayCharge->inventoryMovement->only(['movement_type', 'quantity', 'unit_cost', 'balance_after', 'reference']))->toBe([
            'movement_type' => InventoryMovementType::Sale,
            'quantity' => '2.000',
            'unit_cost' => '6.0000',
            'balance_after' => '8.000',
            'reference' => "Estadía #{$this->stay->id}",
        ])
        ->and($this->balance->fresh()->quantity)->toBe('8.000')
        ->and((string) app(FolioBalanceService::class)->calcular($this->stay->folio))->toBe('25.00');
    $this->assertDatabaseHas('activity_log', ['event' => 'stay_charge.product_sold', 'subject_id' => $stayCharge->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'inventory.sale', 'subject_id' => $stayCharge->inventory_movement_id]);
});

it('does not charge the guest when the warehouse has no stock', function () {
    expect(fn () => venderAlHuesped('10.5'))->toThrow(BusinessRuleException::class, 'quedan 10.000 (botella)');

    expect($this->stay->charges()->count())->toBe(0)
        ->and(InventoryMovement::count())->toBe(0);
});

it('returns the stock at its original cost when the sale is voided', function () {
    $stayCharge = venderAlHuesped('3');
    app(InventoryService::class)->registrarEntrada(
        $this->minibar, $this->product, $this->receptionist, '7', '9.00', (string) Str::uuid(),
    );

    app(ChargeService::class)->anularCargo($stayCharge, $this->receptionist, 'Producto equivocado');

    $return = InventoryMovement::query()->latest('id')->first();
    expect($return->only(['movement_type', 'direction', 'quantity', 'unit_cost', 'balance_after', 'reference']))->toBe([
        'movement_type' => InventoryMovementType::SaleReturn,
        'direction' => InventoryMovementDirection::In,
        'quantity' => '3.000',
        'unit_cost' => '6.0000',
        'balance_after' => '17.000',
        'reference' => "Anulación del cargo #{$stayCharge->id}",
    ])
        // (14 × 7.5 + 3 × 6) / 17 = 7.2353
        ->and($this->balance->fresh()->only(['quantity', 'average_cost']))->toBe(['quantity' => '17.000', 'average_cost' => '7.2353'])
        ->and((string) app(FolioBalanceService::class)->calcular($this->stay->folio))->toBe('0.00');
});

it('only sells active products marked for sale', function () {
    $supply = Product::factory()->create();
    InventoryBalance::factory()->for($this->minibar)->for($supply)->create();

    expect(fn () => venderAlHuesped('1', product: $supply))->toThrow(BusinessRuleException::class, 'El producto no se vende a huéspedes.');

    $this->product->update(['is_active' => false]);
    expect(fn () => venderAlHuesped('1'))->toThrow(BusinessRuleException::class, 'El producto está inactivo.');
});

it('only takes products out of a warehouse of the stay branch', function () {
    $otherWarehouse = Warehouse::factory()->create();
    InventoryBalance::factory()->for($otherWarehouse)->for($this->product)->create();

    venderAlHuesped('1', warehouse: $otherWarehouse);
})->throws(BusinessRuleException::class, 'El almacén no pertenece a la sucursal de la estadía.');

it('does not sell to a stay that is no longer active', function () {
    $completedStay = Stay::factory()->for(Room::factory()->for($this->branch))->finalizada()->create();

    expect(fn () => venderAlHuesped('1', stay: $completedStay))->toThrow(BusinessRuleException::class);

    expect($this->balance->fresh()->quantity)->toBe('10.000');
});

it('no longer charges products by hand', function () {
    app(ChargeService::class)->registrarCargo($this->stay, $this->receptionist, ChargeType::Product, 'Agua', '1', '8.00');
})->throws(BusinessRuleException::class, 'Ese tipo de cargo no se puede registrar manualmente.');
