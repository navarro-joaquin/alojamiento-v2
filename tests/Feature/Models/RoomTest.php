<?php

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Models\Room;
use App\Models\Stay;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('decides whether the room can be assigned from its own state', function (OperationalStatus $operationalStatus, HousekeepingStatus $housekeepingStatus, bool $isAssignable) {
    $room = Room::factory()->make([
        'operational_status' => $operationalStatus,
        'housekeeping_status' => $housekeepingStatus,
    ]);

    expect($room->estaDisponibleParaAsignar())->toBe($isAssignable);
})->with([
    'clean and in service' => [OperationalStatus::InService, HousekeepingStatus::Clean, true],
    'inspected and in service' => [OperationalStatus::InService, HousekeepingStatus::Inspected, true],
    'dirty' => [OperationalStatus::InService, HousekeepingStatus::Dirty, false],
    'under maintenance' => [OperationalStatus::Maintenance, HousekeepingStatus::Clean, false],
    'out of service' => [OperationalStatus::OutOfService, HousekeepingStatus::Clean, false],
]);

it('returns only the active stay as the current occupant', function () {
    $room = Room::factory()->create();
    Stay::factory()->for($room)->finalizada()->create();
    $activeStay = Stay::factory()->for($room)->create();

    expect($room->activeStay->is($activeStay))->toBeTrue();
});

it('has no current occupant when every stay is completed', function () {
    $room = Room::factory()->create();
    Stay::factory()->for($room)->finalizada()->create();

    expect($room->activeStay)->toBeNull();
});
