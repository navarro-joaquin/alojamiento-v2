<?php

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Enums\RoomStatusField;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes the housekeeping status and records who changed it', function () {
    $room = Room::factory()->sucia()->create();
    $user = User::factory()->create();

    $updatedRoom = app(HousekeepingService::class)->cambiarEstadoLimpieza($room, HousekeepingStatus::Clean, $user, 'Limpieza terminada');

    $statusLog = $room->statusLogs()->sole();
    expect($updatedRoom->housekeeping_status)->toBe(HousekeepingStatus::Clean)
        ->and($statusLog->field)->toBe(RoomStatusField::Housekeeping)
        ->and($statusLog->old_value)->toBe('dirty')
        ->and($statusLog->new_value)->toBe('clean')
        ->and($statusLog->changed_by)->toBe($user->id)
        ->and($statusLog->reason)->toBe('Limpieza terminada');
});

it('changes the operational status and records it', function () {
    $room = Room::factory()->create();

    app(HousekeepingService::class)->cambiarEstadoOperativo($room, OperationalStatus::Maintenance, User::factory()->create());

    expect($room->fresh()->operational_status)->toBe(OperationalStatus::Maintenance)
        ->and($room->statusLogs()->sole()->field)->toBe(RoomStatusField::Operational);
});

it('does not record a change when the status is already the requested one', function () {
    $room = Room::factory()->create();

    app(HousekeepingService::class)->cambiarEstadoLimpieza($room, HousekeepingStatus::Clean, User::factory()->create());

    expect($room->statusLogs()->exists())->toBeFalse();
});
