<?php

use App\Enums\BranchRole;
use App\Enums\HousekeepingStatus;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
});

function usuarioDeLaSucursal(Branch $branch, BranchRole $role = BranchRole::Receptionist): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('shows the rooms of the active branch with their occupant', function () {
    $occupiedRoom = Room::factory()->for($this->branch)->create(['room_number' => '101', 'floor' => '1']);
    $stay = Stay::factory()->for($occupiedRoom)->create();
    $stay->guests()->attach(Guest::factory()->create(['first_name' => 'Ana', 'last_name' => 'Quispe']), ['is_primary' => true]);
    Room::factory()->for($this->branch)->sucia()->create(['room_number' => '102', 'floor' => '1']);
    Room::factory()->create(['room_number' => '999']);

    $this->actingAs(usuarioDeLaSucursal($this->branch))
        ->get(route('occupancy.index'))
        ->assertOk()
        ->assertSeeInOrder(['101', 'Ocupada', 'Ana Quispe', '102', 'Sucia'])
        ->assertDontSee('999')
        ->assertViewHas('summary', ['Ocupadas' => 1, 'Disponibles' => 0, 'Sucias' => 1, 'Fuera de servicio' => 0]);
});

it('offers the housekeeping form only to users allowed to change it', function (BranchRole $role, bool $seesForm) {
    $room = Room::factory()->for($this->branch)->create();

    $response = $this->actingAs(usuarioDeLaSucursal($this->branch, $role))->get(route('occupancy.index'));

    $seesForm
        ? $response->assertSee(route('rooms.housekeeping-status.update', $room))
        : $response->assertDontSee(route('rooms.housekeeping-status.update', $room));
})->with([
    'housekeeping' => [BranchRole::Housekeeping, true],
    'storekeeper' => [BranchRole::Storekeeper, false],
]);

it('changes the housekeeping status of a room', function () {
    $room = Room::factory()->for($this->branch)->sucia()->create(['room_number' => '204']);

    $this->actingAs(usuarioDeLaSucursal($this->branch, BranchRole::Housekeeping))
        ->from(route('occupancy.index'))
        ->put(route('rooms.housekeeping-status.update', $room), ['housekeeping_status' => HousekeepingStatus::Clean->value])
        ->assertRedirect(route('occupancy.index'))
        ->assertSessionHas('status', 'Habitación 204: Limpia.');

    expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Clean)
        ->and($room->statusLogs()->count())->toBe(1);
});

it('forbids changing the housekeeping status without permission', function () {
    $room = Room::factory()->for($this->branch)->sucia()->create();

    $this->actingAs(usuarioDeLaSucursal($this->branch, BranchRole::Storekeeper))
        ->put(route('rooms.housekeeping-status.update', $room), ['housekeeping_status' => HousekeepingStatus::Clean->value])
        ->assertForbidden();

    expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty);
});

it('forbids changing a room of another branch', function () {
    $room = Room::factory()->sucia()->create();

    $this->actingAs(usuarioDeLaSucursal($this->branch, BranchRole::Admin))
        ->put(route('rooms.housekeeping-status.update', $room), ['housekeeping_status' => HousekeepingStatus::Clean->value])
        ->assertForbidden();
});

it('rejects an unknown housekeeping status', function () {
    $room = Room::factory()->for($this->branch)->create();

    $this->actingAs(usuarioDeLaSucursal($this->branch, BranchRole::Housekeeping))
        ->put(route('rooms.housekeeping-status.update', $room), ['housekeeping_status' => 'brillante'])
        ->assertSessionHasErrors('housekeeping_status');
});
