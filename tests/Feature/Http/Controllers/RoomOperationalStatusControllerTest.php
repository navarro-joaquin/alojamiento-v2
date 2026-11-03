<?php

use App\Enums\BranchRole;
use App\Enums\OperationalStatus;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->room = Room::factory()->create(['room_number' => '110']);
});

function encargadoDeHabitaciones(Room $room, BranchRole $role = BranchRole::Supervisor): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $room->branch_id);

    return $user;
}

it('takes a room out of service with a reason', function () {
    $this->actingAs(encargadoDeHabitaciones($this->room))
        ->from(route('occupancy.index'))
        ->put(route('rooms.operational-status.update', $this->room), ['operational_status' => 'maintenance', 'reason' => 'Fuga de agua'])
        ->assertRedirect(route('occupancy.index'))
        ->assertSessionHas('status', 'Habitación 110: En mantenimiento.');

    expect($this->room->fresh()->operational_status)->toBe(OperationalStatus::Maintenance)
        ->and($this->room->statusLogs()->sole()->reason)->toBe('Fuga de agua');
});

it('requires a reason to take a room out of service', function () {
    $this->actingAs(encargadoDeHabitaciones($this->room))
        ->put(route('rooms.operational-status.update', $this->room), ['operational_status' => 'out_of_service'])
        ->assertSessionHasErrors('reason');
});

it('returns a room to service without a reason', function () {
    $this->room->update(['operational_status' => OperationalStatus::Maintenance]);

    $this->actingAs(encargadoDeHabitaciones($this->room))
        ->put(route('rooms.operational-status.update', $this->room), ['operational_status' => 'in_service'])
        ->assertSessionHasNoErrors();

    expect($this->room->fresh()->operational_status)->toBe(OperationalStatus::InService);
});

it('forbids the receptionist from changing the operational status', function () {
    $this->actingAs(encargadoDeHabitaciones($this->room, BranchRole::Receptionist))
        ->put(route('rooms.operational-status.update', $this->room), ['operational_status' => 'maintenance', 'reason' => 'Fuga'])
        ->assertForbidden();
});
