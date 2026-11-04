<?php

use App\Enums\InventoryMovementDirection;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->depot = Warehouse::factory()->create(['name' => 'Depósito']);
    $this->minibar = Warehouse::factory()->for(Branch::factory())->create(['name' => 'Minibar Norte']);
    $this->water = Product::factory()->create(['name' => 'Agua', 'unit' => 'botella']);
    $this->soap = Product::factory()->create(['name' => 'Jabón', 'unit' => 'pieza']);
    InventoryBalance::factory()->for($this->depot)->for($this->water)->create(['quantity' => '20.000', 'average_cost' => '4.0000']);
    InventoryBalance::factory()->for($this->depot)->for($this->soap)->create(['quantity' => '50.000', 'average_cost' => '1.5000']);
    $this->storekeeper = User::factory()->create();
});

function crearTransferencia(array $quantitiesByProduct, ?Warehouse $toWarehouse = null): InventoryTransfer
{
    $test = test();

    return app(InventoryTransferService::class)->crear($test->depot, $toWarehouse ?? $test->minibar, $test->storekeeper, $quantitiesByProduct, 'Reposición semanal');
}

function existenciasDe(Warehouse $warehouse, Product $product): ?string
{
    return InventoryBalance::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('quantity');
}

it('creates a draft that moves no stock', function () {
    $transfer = crearTransferencia([$this->water->id => '6', $this->soap->id => '10']);

    expect($transfer->status)->toBe(InventoryTransferStatus::Draft)
        ->and($transfer->items()->pluck('quantity', 'product_id')->all())->toBe([$this->water->id => '6.000', $this->soap->id => '10.000'])
        ->and(InventoryMovement::count())->toBe(0);
    $this->assertDatabaseHas('activity_log', ['event' => 'inventory_transfer.created', 'subject_id' => $transfer->id]);
});

it('takes the stock out of the source at its average cost when sent', function () {
    $transfer = crearTransferencia([$this->water->id => '6', $this->soap->id => '10']);

    $sent = app(InventoryTransferService::class)->enviar($transfer, $this->storekeeper);

    expect($sent->status)->toBe(InventoryTransferStatus::Sent)
        ->and($sent->sent_by)->toBe($this->storekeeper->id)
        ->and(existenciasDe($this->depot, $this->water))->toBe('14.000')
        ->and(existenciasDe($this->depot, $this->soap))->toBe('40.000')
        ->and(existenciasDe($this->minibar, $this->water))->toBeNull()
        ->and($sent->items()->pluck('unit_cost', 'product_id')->all())->toBe([$this->water->id => '4.0000', $this->soap->id => '1.5000'])
        ->and($sent->movements()->pluck('movement_type')->unique()->all())->toBe([InventoryMovementType::TransferOut]);
});

it('sends nothing when a product lacks stock', function () {
    $transfer = crearTransferencia([$this->water->id => '6', $this->soap->id => '51']);

    expect(fn () => app(InventoryTransferService::class)->enviar($transfer, $this->storekeeper))
        ->toThrow(BusinessRuleException::class, 'quedan 50.000 (pieza)');

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Draft)
        ->and(existenciasDe($this->depot, $this->water))->toBe('20.000')
        ->and(InventoryMovement::count())->toBe(0);
});

it('puts the full quantity into the destination at the cost it left with', function () {
    $transfer = crearTransferencia([$this->water->id => '6']);
    app(InventoryTransferService::class)->enviar($transfer, $this->storekeeper);
    $receiver = User::factory()->create();

    $received = app(InventoryTransferService::class)->recibir($transfer, $receiver);

    $destinationBalance = InventoryBalance::query()->where('warehouse_id', $this->minibar->id)->sole();
    expect($received->status)->toBe(InventoryTransferStatus::Received)
        ->and($received->received_by)->toBe($receiver->id)
        ->and($received->items()->value('received_quantity'))->toBe('6.000')
        ->and($destinationBalance->only(['quantity', 'average_cost']))->toBe(['quantity' => '6.000', 'average_cost' => '4.0000']);
    $this->assertDatabaseHas('activity_log', ['event' => 'inventory_transfer.received', 'subject_id' => $transfer->id, 'branch_id' => $this->minibar->branch_id]);
});

it('records a shortage on reception as an adjustment of the destination with its reason', function () {
    $transfer = crearTransferencia([$this->water->id => '6', $this->soap->id => '10']);
    app(InventoryTransferService::class)->enviar($transfer, $this->storekeeper);

    app(InventoryTransferService::class)->recibir($transfer, $this->storekeeper, [$this->water->id => '4.5'], 'Dos botellas rotas');

    $shortage = InventoryMovement::query()->where('movement_type', InventoryMovementType::Adjustment)->sole();
    expect(existenciasDe($this->minibar, $this->water))->toBe('4.500')
        ->and(existenciasDe($this->minibar, $this->soap))->toBe('10.000')
        ->and(existenciasDe($this->depot, $this->water))->toBe('14.000')
        ->and($shortage->only(['warehouse_id', 'direction', 'quantity', 'inventory_transfer_id', 'notes']))->toBe([
            'warehouse_id' => $this->minibar->id,
            'direction' => InventoryMovementDirection::Out,
            'quantity' => '1.500',
            'inventory_transfer_id' => $transfer->id,
            'notes' => 'Faltante en la recepción: Dos botellas rotas',
        ])
        ->and($transfer->fresh()->reception_notes)->toBe('Dos botellas rotas');
});

it('requires the reason of a shortage and a received quantity within the sent one', function () {
    $transfer = crearTransferencia([$this->water->id => '6']);
    app(InventoryTransferService::class)->enviar($transfer, $this->storekeeper);

    expect(fn () => app(InventoryTransferService::class)->recibir($transfer, $this->storekeeper, [$this->water->id => '5']))
        ->toThrow(BusinessRuleException::class, 'Indique el motivo del faltante en la recepción.')
        ->and(fn () => app(InventoryTransferService::class)->recibir($transfer, $this->storekeeper, [$this->water->id => '6.001'], 'Sobra'))
        ->toThrow(BusinessRuleException::class, 'La cantidad recibida debe estar entre cero y la enviada.');

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Sent);
});

it('follows the order draft, sent and received', function () {
    $transfer = crearTransferencia([$this->water->id => '6']);
    $service = app(InventoryTransferService::class);

    expect(fn () => $service->recibir($transfer, $this->storekeeper))->toThrow(BusinessRuleException::class, 'La transferencia no está en tránsito.');

    $service->enviar($transfer, $this->storekeeper);

    expect(fn () => $service->enviar($transfer, $this->storekeeper))->toThrow(BusinessRuleException::class, 'La transferencia ya no está en borrador.')
        ->and(fn () => $service->cancelar($transfer, $this->storekeeper))->toThrow(BusinessRuleException::class, 'La transferencia ya no está en borrador.');
});

it('cancels a draft without moving stock', function () {
    $transfer = crearTransferencia([$this->water->id => '6']);

    $cancelled = app(InventoryTransferService::class)->cancelar($transfer, $this->storekeeper);

    expect($cancelled->status)->toBe(InventoryTransferStatus::Cancelled)
        ->and($cancelled->cancelled_by)->toBe($this->storekeeper->id)
        ->and(InventoryMovement::count())->toBe(0);
});

it('validates the warehouses and products of a new transfer', function () {
    expect(fn () => crearTransferencia([$this->water->id => '1'], $this->depot))
        ->toThrow(BusinessRuleException::class, 'El almacén de destino debe ser distinto del de origen.')
        ->and(fn () => crearTransferencia([]))
        ->toThrow(BusinessRuleException::class, 'La transferencia no tiene productos.')
        ->and(fn () => crearTransferencia([$this->water->id => '0']))
        ->toThrow(BusinessRuleException::class, 'La cantidad debe ser mayor que cero.');

    $this->soap->update(['is_active' => false]);
    expect(fn () => crearTransferencia([$this->soap->id => '1']))->toThrow(BusinessRuleException::class, 'El producto está inactivo.');

    $this->minibar->update(['is_active' => false]);
    expect(fn () => crearTransferencia([$this->water->id => '1']))->toThrow(BusinessRuleException::class, 'El almacén está inactivo.');
});
