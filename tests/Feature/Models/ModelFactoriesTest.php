<?php

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Employee;
use App\Models\Guest;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomStatusLog;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\StayRoomAssignment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  class-string<Model>  $modelClass
 */
test('the factory persists a valid record', function (string $modelClass) {
    $model = $modelClass::factory()->create();

    expect($model->exists)->toBeTrue();
    $this->assertModelExists($model);
})->with([
    'branch' => Branch::class,
    'employee' => Employee::class,
    'user' => User::class,
    'room type' => RoomType::class,
    'room' => Room::class,
    'room status log' => RoomStatusLog::class,
    'room rate' => RoomRate::class,
    'guest' => Guest::class,
    'stay' => Stay::class,
    'stay room assignment' => StayRoomAssignment::class,
    'stay charge' => StayCharge::class,
    'cash register' => CashRegister::class,
    'cash session' => CashSession::class,
    'payment' => Payment::class,
    'payment refund' => PaymentRefund::class,
    'cash movement' => CashMovement::class,
    'product category' => ProductCategory::class,
    'product' => Product::class,
    'warehouse' => Warehouse::class,
    'inventory balance' => InventoryBalance::class,
    'inventory movement' => InventoryMovement::class,
    'inventory transfer' => InventoryTransfer::class,
    'inventory transfer item' => InventoryTransferItem::class,
]);

test('a stay belongs to the branch of its room', function () {
    $stay = Stay::factory()->create();

    expect($stay->branch_id)->toBe($stay->room->branch_id);
});

test('a payment is collected in a cash register of the stay branch', function () {
    $payment = Payment::factory()->create();

    expect($payment->cashSession->cashRegister->branch_id)->toBe($payment->stay->branch_id);
});
