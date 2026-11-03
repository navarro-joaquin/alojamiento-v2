<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $now = now();

    $this->userId = DB::table('users')->insertGetId([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => bcrypt('password'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->branchId = DB::table('branches')->insertGetId([
        'code' => 'LPZ',
        'name' => 'La Paz',
        'current_business_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $roomTypeId = DB::table('room_types')->insertGetId([
        'name' => 'Doble',
        'default_capacity' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->roomId = DB::table('rooms')->insertGetId([
        'branch_id' => $this->branchId,
        'room_type_id' => $roomTypeId,
        'room_number' => '101',
        'capacity' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->cashRegisterId = DB::table('cash_registers')->insertGetId([
        'branch_id' => $this->branchId,
        'name' => 'Recepción',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function insertarEstadia(int $branchId, int $roomId, int $userId, array $attributes = []): int
{
    return DB::table('stays')->insertGetId([
        'branch_id' => $branchId,
        'room_id' => $roomId,
        'created_by' => $userId,
        'check_in_at' => now(),
        'rate_amount' => '250.00',
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertarSesionCaja(int $cashRegisterId, int $userId, array $attributes = []): int
{
    return DB::table('cash_sessions')->insertGetId([
        'cash_register_id' => $cashRegisterId,
        'opened_by' => $userId,
        'business_date' => now()->toDateString(),
        'opened_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertarCargoAlojamiento(int $stayId, int $userId, array $attributes = []): int
{
    return DB::table('stay_charges')->insertGetId([
        'stay_id' => $stayId,
        'charge_type' => 'lodging',
        'description' => 'Noche',
        'service_date' => '2026-11-03',
        'unit_price' => '250.00',
        'total_amount' => '250.00',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

test('a room cannot have two active stays', function () {
    insertarEstadia($this->branchId, $this->roomId, $this->userId);

    insertarEstadia($this->branchId, $this->roomId, $this->userId);
})->throws(QueryException::class, 'Duplicate entry');

test('a room can have a new active stay once the previous one is completed', function () {
    insertarEstadia($this->branchId, $this->roomId, $this->userId, ['status' => 'completed']);
    insertarEstadia($this->branchId, $this->roomId, $this->userId);

    expect(DB::table('stays')->where('room_id', $this->roomId)->count())->toBe(2);
});

test('a cash register cannot have two open sessions', function () {
    insertarSesionCaja($this->cashRegisterId, $this->userId);

    insertarSesionCaja($this->cashRegisterId, $this->userId, ['status' => 'closing']);
})->throws(QueryException::class, 'Duplicate entry');

test('a cash register can open a new session after the previous one is closed', function () {
    insertarSesionCaja($this->cashRegisterId, $this->userId, ['status' => 'closed']);
    insertarSesionCaja($this->cashRegisterId, $this->userId);

    expect(DB::table('cash_sessions')->count())->toBe(2);
});

test('a stay cannot be charged twice for the same lodging night', function () {
    $stayId = insertarEstadia($this->branchId, $this->roomId, $this->userId);
    insertarCargoAlojamiento($stayId, $this->userId);

    insertarCargoAlojamiento($stayId, $this->userId);
})->throws(QueryException::class, 'Duplicate entry');

test('a voided lodging night can be charged again', function () {
    $stayId = insertarEstadia($this->branchId, $this->roomId, $this->userId);
    insertarCargoAlojamiento($stayId, $this->userId, ['status' => 'voided']);
    insertarCargoAlojamiento($stayId, $this->userId);

    expect(DB::table('stay_charges')->where('stay_id', $stayId)->count())->toBe(2);
});

test('a stay cannot have two primary guests', function () {
    $stayId = insertarEstadia($this->branchId, $this->roomId, $this->userId);

    foreach (['111', '222'] as $documentNumber) {
        $guestId = DB::table('guests')->insertGetId([
            'document_type' => 'ci',
            'document_number' => $documentNumber,
            'issuing_country' => 'BO',
            'document_number_hash' => hash('sha256', $documentNumber),
            'first_name' => 'Huésped',
            'last_name' => $documentNumber,
        ]);

        DB::table('stay_guests')->insert([
            'stay_id' => $stayId,
            'guest_id' => $guestId,
            'is_primary' => true,
        ]);
    }
})->throws(QueryException::class, 'Duplicate entry');

test('payments reject non-positive amounts', function () {
    $stayId = insertarEstadia($this->branchId, $this->roomId, $this->userId);
    $cashSessionId = insertarSesionCaja($this->cashRegisterId, $this->userId);

    DB::table('payments')->insert([
        'stay_id' => $stayId,
        'cash_session_id' => $cashSessionId,
        'created_by' => $this->userId,
        'payment_method' => 'cash',
        'payment_type' => 'settlement',
        'amount' => '0.00',
        'operation_uuid' => (string) Str::uuid(),
        'request_hash' => hash('sha256', 'payment'),
        'paid_at' => now(),
    ]);
})->throws(QueryException::class, 'payments_amount_positive');

test('cash movements reject an invalid direction', function () {
    $cashSessionId = insertarSesionCaja($this->cashRegisterId, $this->userId);

    DB::table('cash_movements')->insert([
        'cash_session_id' => $cashSessionId,
        'performed_by' => $this->userId,
        'movement_type' => 'payment',
        'direction' => 'sideways',
        'amount' => '10.00',
        'source_type' => 'payment',
        'source_id' => 1,
        'operation_uuid' => (string) Str::uuid(),
        'occurred_at' => now(),
    ]);
})->throws(QueryException::class, 'cash_movements_direction_valid');

test('the activity log exposes the branch id from its properties', function () {
    DB::table('activity_log')->insert([
        'description' => 'payment.collected',
        'properties' => json_encode(['branch_id' => $this->branchId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('activity_log')->value('branch_id'))->toBe($this->branchId);
});
