<?php

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Guest;
use App\Models\Payment;
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
