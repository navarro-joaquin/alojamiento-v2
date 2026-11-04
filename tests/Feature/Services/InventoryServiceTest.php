<?php

use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create(['name' => 'Depósito']);
    $this->product = Product::factory()->create(['name' => 'Papel higiénico', 'unit' => 'rollo']);
    $this->storekeeper = User::factory()->create();
});

function registrarEntrada(string $quantity, string $unitCost, ?string $operationUuid = null): InventoryMovement
{
    $test = test();

    return app(InventoryService::class)->registrarEntrada(
        $test->warehouse, $test->product, $test->storekeeper, $quantity, $unitCost, $operationUuid ?? (string) Str::uuid(), 'Factura 123',
    );
}

function registrarSalida(string $quantity, InventoryMovementType $movementType = InventoryMovementType::Consumption, ?string $operationUuid = null): InventoryMovement
{
    $test = test();

    return app(InventoryService::class)->registrarSalida(
        $test->warehouse, $test->product, $test->storekeeper, $movementType, $quantity, $operationUuid ?? (string) Str::uuid(),
    );
}

function registrarAjuste(string $countedQuantity, ?string $operationUuid = null): InventoryMovement
{
    $test = test();

    return app(InventoryService::class)->registrarAjuste(
        $test->warehouse, $test->product, $test->storekeeper, $countedQuantity, 'Conteo mensual', $operationUuid ?? (string) Str::uuid(),
    );
}

function saldoDeInventario(): InventoryBalance
{
    return InventoryBalance::query()->where('warehouse_id', test()->warehouse->id)->where('product_id', test()->product->id)->sole();
}

it('creates the balance with the first entry', function () {
    $movement = registrarEntrada('24', '3.50');

    expect($movement->movement_type)->toBe(InventoryMovementType::Entry)
        ->and($movement->direction)->toBe(InventoryMovementDirection::In)
        ->and($movement->quantity)->toBe('24.000')
        ->and($movement->unit_cost)->toBe('3.5000')
        ->and($movement->balance_after)->toBe('24.000')
        ->and($movement->reference)->toBe('Factura 123')
        ->and(saldoDeInventario()->only(['quantity', 'average_cost']))->toBe(['quantity' => '24.000', 'average_cost' => '3.5000']);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'inventory', 'event' => 'inventory.entry', 'subject_id' => $movement->id]);
});

it('recomputes the weighted average cost with each entry', function () {
    registrarEntrada('10', '4.00');
    registrarEntrada('30', '6.00');

    // (10 × 4 + 30 × 6) / 40 = 5.5
    expect(saldoDeInventario()->only(['quantity', 'average_cost']))->toBe(['quantity' => '40.000', 'average_cost' => '5.5000']);
});

it('takes exits out at the average cost without changing it', function () {
    registrarEntrada('10', '4.00');
    registrarEntrada('30', '6.00');

    $movement = registrarSalida('15.5');

    expect($movement->movement_type)->toBe(InventoryMovementType::Consumption)
        ->and($movement->direction)->toBe(InventoryMovementDirection::Out)
        ->and($movement->unit_cost)->toBe('5.5000')
        ->and($movement->balance_after)->toBe('24.500')
        ->and(saldoDeInventario()->only(['quantity', 'average_cost']))->toBe(['quantity' => '24.500', 'average_cost' => '5.5000']);
});

it('rejects an exit larger than the stock', function () {
    registrarEntrada('5', '4.00');

    expect(fn () => registrarSalida('5.001', InventoryMovementType::Exit))
        ->toThrow(BusinessRuleException::class, 'quedan 5.000 (rollo)');

    expect(saldoDeInventario()->quantity)->toBe('5.000')
        ->and(InventoryMovement::count())->toBe(1);
});

it('lets an exit empty the stock and keeps the last average cost', function () {
    registrarEntrada('5', '4.00');

    registrarSalida('5');

    expect(saldoDeInventario()->only(['quantity', 'average_cost']))->toBe(['quantity' => '0.000', 'average_cost' => '4.0000']);
});

it('only registers exits and consumptions by hand', function (InventoryMovementType $movementType) {
    registrarEntrada('5', '4.00');

    registrarSalida('1', $movementType);
})->with([
    InventoryMovementType::Sale,
    InventoryMovementType::TransferOut,
    InventoryMovementType::Adjustment,
])->throws(BusinessRuleException::class, 'Ese tipo de salida no se puede registrar manualmente.');

it('adjusts the stock to a physical count in either direction', function () {
    registrarEntrada('10', '4.00');

    $shortage = registrarAjuste('7.5');
    $surplus = registrarAjuste('9');

    expect($shortage->only(['direction', 'quantity', 'unit_cost', 'balance_after']))
        ->toBe(['direction' => InventoryMovementDirection::Out, 'quantity' => '2.500', 'unit_cost' => '4.0000', 'balance_after' => '7.500'])
        ->and($surplus->only(['direction', 'quantity', 'unit_cost', 'balance_after']))
        ->toBe(['direction' => InventoryMovementDirection::In, 'quantity' => '1.500', 'unit_cost' => '4.0000', 'balance_after' => '9.000'])
        ->and($surplus->notes)->toBe('Conteo mensual')
        ->and(saldoDeInventario()->average_cost)->toBe('4.0000');
    $this->assertDatabaseHas('activity_log', ['event' => 'inventory.adjustment', 'subject_id' => $shortage->id]);
});

it('rejects an adjustment that matches the stock', function () {
    registrarEntrada('10', '4.00');

    registrarAjuste('10.000');
})->throws(BusinessRuleException::class, 'no hay nada que ajustar');

it('registers a repeated manual movement only once', function () {
    $operationUuid = (string) Str::uuid();

    $first = registrarEntrada('10', '4.00', $operationUuid);
    $retry = registrarEntrada('10', '4.00', $operationUuid);

    expect($retry->is($first))->toBeTrue()
        ->and(saldoDeInventario()->quantity)->toBe('10.000');

    expect(fn () => registrarEntrada('11', '4.00', $operationUuid))->toThrow(IdempotencyConflictException::class);
});

it('rejects movements of an inactive warehouse or product', function () {
    $this->product->update(['is_active' => false]);
    expect(fn () => registrarEntrada('1', '1.00'))->toThrow(BusinessRuleException::class, 'El producto está inactivo.');

    $this->product->update(['is_active' => true]);
    $this->warehouse->update(['is_active' => false]);
    expect(fn () => registrarEntrada('1', '1.00'))->toThrow(BusinessRuleException::class, 'El almacén está inactivo.');
});

it('rejects quantities that are not positive and negative costs', function () {
    expect(fn () => registrarEntrada('0.0004', '1.00'))->toThrow(BusinessRuleException::class, 'La cantidad debe ser mayor que cero.')
        ->and(fn () => registrarEntrada('1', '-0.01'))->toThrow(BusinessRuleException::class, 'El costo unitario no puede ser negativo.')
        ->and(fn () => registrarAjuste('-1'))->toThrow(BusinessRuleException::class, 'La cantidad contada no puede ser negativa.');
});

it('keeps the stock of each warehouse apart', function () {
    registrarEntrada('10', '4.00');
    $otherWarehouse = Warehouse::factory()->for($this->warehouse->branch)->create();

    expect(fn () => app(InventoryService::class)->registrarSalida(
        $otherWarehouse, $this->product, $this->storekeeper, InventoryMovementType::Exit, '1', (string) Str::uuid(),
    ))->toThrow(BusinessRuleException::class, 'quedan 0.000 (rollo)');
});
