<?php

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a room cannot have two active stays', function () {
    $room = Room::factory()->create();
    Stay::factory()->for($room)->create();

    Stay::factory()->for($room)->create();
})->throws(QueryException::class, 'Duplicate entry');

test('a room can have a new active stay once the previous one is completed', function () {
    $room = Room::factory()->create();
    Stay::factory()->for($room)->finalizada()->create();

    Stay::factory()->for($room)->create();

    expect($room->stays()->count())->toBe(2);
});

test('a cash register cannot have two open sessions', function () {
    $cashRegister = CashRegister::factory()->create();
    CashSession::factory()->for($cashRegister)->create();

    CashSession::factory()->for($cashRegister)->enCierre()->create();
})->throws(QueryException::class, 'Duplicate entry');

test('a cash register can open a new session after the previous one is closed', function () {
    $cashRegister = CashRegister::factory()->create();
    CashSession::factory()->for($cashRegister)->cerrada()->create();

    CashSession::factory()->for($cashRegister)->create();

    expect($cashRegister->sessions()->count())->toBe(2);
});

test('a stay cannot be charged twice for the same lodging night', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03']);

    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03']);
})->throws(QueryException::class, 'Duplicate entry');

test('a voided lodging night can be charged again', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->anulado()->create(['service_date' => '2026-11-03']);

    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03']);

    expect($stay->charges()->count())->toBe(2);
});

test('a stay cannot have two primary guests', function () {
    $stay = Stay::factory()->create();

    $stay->guests()->attach(Guest::factory()->create(), ['is_primary' => true]);
    $stay->guests()->attach(Guest::factory()->create(), ['is_primary' => true]);
})->throws(QueryException::class, 'Duplicate entry');

test('payments reject non-positive amounts', function () {
    Payment::factory()->create(['amount' => '0.00']);
})->throws(QueryException::class, 'payments_amount_positive');

test('expenses reject non-positive amounts', function () {
    Expense::factory()->create(['amount' => '0.00']);
})->throws(QueryException::class, 'expenses_amount_positive');

test('an expense cannot be registered twice with the same operation uuid', function () {
    $expense = Expense::factory()->create();

    Expense::factory()->create(['operation_uuid' => $expense->operation_uuid]);
})->throws(QueryException::class, 'Duplicate entry');

test('cash movements reject an invalid direction', function () {
    $cashMovement = CashMovement::factory()->create();

    DB::table('cash_movements')->where('id', $cashMovement->id)->update(['direction' => 'sideways']);
})->throws(QueryException::class, 'cash_movements_direction_valid');

test('the activity log exposes the branch id from its properties', function () {
    $stay = Stay::factory()->create();

    activity('reception')
        ->performedOn($stay)
        ->withProperties(['branch_id' => $stay->branch_id])
        ->log('stay.checked_in');

    expect(DB::table('activity_log')->value('branch_id'))->toBe($stay->branch_id);
});

test('a product sold to guests needs a sale price', function () {
    Product::factory()->create(['is_sellable' => true, 'sale_price' => null]);
})->throws(QueryException::class, 'products_sellable_has_price');

test('a product sale price must be positive', function () {
    Product::factory()->create(['sale_price' => '0.00']);
})->throws(QueryException::class, 'products_sale_price_positive');

test('a product has a single balance per warehouse', function () {
    $balance = InventoryBalance::factory()->create();

    InventoryBalance::factory()->create(['warehouse_id' => $balance->warehouse_id, 'product_id' => $balance->product_id]);
})->throws(QueryException::class, 'Duplicate entry');

test('an inventory balance cannot go negative', function () {
    InventoryBalance::factory()->create(['quantity' => '-0.001']);
})->throws(QueryException::class, 'inventory_balances_quantity_not_negative');

test('an inventory movement direction must match its type', function () {
    $movement = InventoryMovement::factory()->create();

    DB::table('inventory_movements')->where('id', $movement->id)->update(['direction' => 'out']);
})->throws(QueryException::class, 'inventory_movements_direction_matches_type');

test('an inventory movement quantity must be positive', function () {
    InventoryMovement::factory()->create(['quantity' => '0.000']);
})->throws(QueryException::class, 'inventory_movements_quantity_positive');

test('a charge linked to a sale must be a product charge naming its product', function () {
    $movement = InventoryMovement::factory()->create(['movement_type' => 'sale', 'direction' => 'out']);

    StayCharge::factory()->create(['charge_type' => 'service', 'product_id' => $movement->product_id, 'inventory_movement_id' => $movement->id]);
})->throws(QueryException::class, 'stay_charges_product_sale_complete');

test('a product charge linked to a sale must name its product', function () {
    $movement = InventoryMovement::factory()->create(['movement_type' => 'sale', 'direction' => 'out']);

    StayCharge::factory()->create(['charge_type' => 'product', 'inventory_movement_id' => $movement->id]);
})->throws(QueryException::class, 'stay_charges_product_sale_complete');

test('an inventory sale is charged only once', function () {
    $movement = InventoryMovement::factory()->create(['movement_type' => 'sale', 'direction' => 'out']);
    $attributes = ['charge_type' => 'product', 'product_id' => $movement->product_id, 'inventory_movement_id' => $movement->id];
    StayCharge::factory()->create($attributes);

    StayCharge::factory()->create($attributes);
})->throws(QueryException::class, 'Duplicate entry');
