<?php

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->roomType = RoomType::factory()->create(['name' => 'Doble']);
    $this->admin = usuarioDeCatalogo($this->branch);
});

function usuarioDeCatalogo(Branch $branch, BranchRole $role = BranchRole::Admin): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeTarifa(RoomType $roomType, array $overrides = []): array
{
    return [
        'room_type_id' => $roomType->id,
        'name' => 'Fin de semana',
        'amount' => '320.50',
        'occupancy' => '',
        'days_of_week' => ['6', '5'],
        'priority' => '10',
        'valid_from' => '2026-11-01',
        'valid_to' => '',
        'is_active' => '1',
        ...$overrides,
    ];
}

it('shows the branch configuration only to its admin', function () {
    $this->actingAs($this->admin)->get(route('occupancy.index'))->assertSee('Configuración')->assertSee(route('room-rates.index'));

    $supervisor = usuarioDeCatalogo($this->branch, BranchRole::Supervisor);
    $this->actingAs($supervisor)->get(route('occupancy.index'))->assertDontSee('Configuración');

    foreach (['rooms.index', 'rooms.create', 'room-rates.index', 'room-rates.create', 'cash-registers.create'] as $routeName) {
        $this->actingAs($supervisor)->get(route($routeName))->assertForbidden();
    }
    $this->actingAs($supervisor)->post(route('rooms.store'), ['room_type_id' => $this->roomType->id, 'room_number' => '101', 'capacity' => 2])->assertForbidden();
    $this->actingAs($supervisor)->post(route('cash-registers.store'), ['name' => 'Caja 2'])->assertForbidden();
    $this->actingAs($supervisor)->post(route('room-rates.store'), datosDeTarifa($this->roomType))->assertForbidden();
});

it('lists and creates rooms of the active branch', function () {
    Room::factory()->create(['room_number' => '999']);

    $this->actingAs($this->admin)
        ->post(route('rooms.store'), ['room_type_id' => $this->roomType->id, 'room_number' => '101', 'floor' => '1', 'capacity' => 2])
        ->assertRedirect(route('rooms.index'));

    $room = Room::query()->whereBelongsTo($this->branch)->sole();
    expect($room->room_number)->toBe('101')->and($room->capacity)->toBe(2);

    $this->actingAs($this->admin)->get(route('rooms.index'))->assertOk()->assertSee('101')->assertDontSee('999');
    $this->actingAs($this->admin)->get(route('rooms.edit', $room))->assertOk();
});

it('keeps room numbers unique per branch', function () {
    Room::factory()->for($this->branch)->create(['room_number' => '101']);
    Room::factory()->create(['room_number' => '102']);

    $this->actingAs($this->admin)
        ->post(route('rooms.store'), ['room_type_id' => $this->roomType->id, 'room_number' => '101', 'capacity' => 2])
        ->assertSessionHasErrors('room_number');

    $this->actingAs($this->admin)
        ->post(route('rooms.store'), ['room_type_id' => $this->roomType->id, 'room_number' => '102', 'capacity' => 2])
        ->assertSessionHasNoErrors();
});

it('updates a room keeping its own number', function () {
    $room = Room::factory()->for($this->branch)->create(['room_number' => '101', 'capacity' => 2]);

    $this->actingAs($this->admin)
        ->put(route('rooms.update', $room), ['room_type_id' => $this->roomType->id, 'room_number' => '101', 'capacity' => 3])
        ->assertRedirect(route('rooms.index'));

    expect($room->refresh()->capacity)->toBe(3)->and($room->room_type_id)->toBe($this->roomType->id);
});

it('does not expose rooms, rates or registers of another branch', function () {
    $otherBranch = Branch::factory()->create();
    $room = Room::factory()->for($otherBranch)->create();
    $roomRate = RoomRate::factory()->for($otherBranch)->create();
    $cashRegister = CashRegister::factory()->for($otherBranch)->create();

    $this->actingAs($this->admin)->get(route('rooms.edit', $room))->assertNotFound();
    $this->actingAs($this->admin)->put(route('rooms.update', $room), ['room_type_id' => $this->roomType->id, 'room_number' => '1', 'capacity' => 1])->assertNotFound();
    $this->actingAs($this->admin)->get(route('room-rates.edit', $roomRate))->assertNotFound();
    $this->actingAs($this->admin)->put(route('room-rates.update', $roomRate), datosDeTarifa($this->roomType))->assertNotFound();
    $this->actingAs($this->admin)->get(route('cash-registers.edit', $cashRegister))->assertNotFound();
    $this->actingAs($this->admin)->put(route('cash-registers.update', $cashRegister), ['name' => 'Ajena'])->assertNotFound();
});

it('creates a rate with sorted weekdays and audits it', function () {
    $this->actingAs($this->admin)
        ->post(route('room-rates.store'), datosDeTarifa($this->roomType))
        ->assertRedirect(route('room-rates.index'));

    $roomRate = RoomRate::query()->sole();
    expect($roomRate->branch_id)->toBe($this->branch->id)
        ->and($roomRate->amount)->toBe('320.50')
        ->and($roomRate->occupancy)->toBeNull()
        ->and($roomRate->days_of_week)->toBe([5, 6])
        ->and($roomRate->valid_to)->toBeNull();
    $this->assertDatabaseHas('activity_log', ['event' => 'room_rate.created', 'subject_id' => $roomRate->id]);

    $this->actingAs($this->admin)->get(route('room-rates.index'))->assertOk()->assertSee('Fin de semana')->assertSee('320.50');
});

it('stores no weekdays as every day', function () {
    $this->actingAs($this->admin)
        ->post(route('room-rates.store'), datosDeTarifa($this->roomType, ['days_of_week' => null]))
        ->assertSessionHasNoErrors();

    expect(RoomRate::query()->sole()->days_of_week)->toBeNull();
});

it('rejects an active rate that overlaps another one', function () {
    RoomRate::factory()->for($this->branch)->for($this->roomType)->create(['priority' => 10, 'days_of_week' => [6], 'valid_from' => '2026-01-01']);

    $this->actingAs($this->admin)
        ->from(route('room-rates.create'))
        ->post(route('room-rates.store'), datosDeTarifa($this->roomType))
        ->assertRedirect(route('room-rates.create'))
        ->assertSessionHasErrors('operation');

    expect(RoomRate::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('room-rates.store'), datosDeTarifa($this->roomType, ['is_active' => '0']))
        ->assertSessionHasNoErrors();
});

it('updates a rate without counting it as its own overlap', function () {
    $roomRate = RoomRate::factory()->for($this->branch)->for($this->roomType)->create(['priority' => 10, 'days_of_week' => [5, 6], 'valid_from' => '2026-11-01']);

    $this->actingAs($this->admin)
        ->put(route('room-rates.update', $roomRate), datosDeTarifa($this->roomType, ['amount' => '350.00']))
        ->assertRedirect(route('room-rates.index'));

    expect($roomRate->refresh()->amount)->toBe('350.00');
    $this->assertDatabaseHas('activity_log', ['event' => 'room_rate.updated', 'subject_id' => $roomRate->id]);
});

it('validates the rate validity range and weekdays', function () {
    $this->actingAs($this->admin)
        ->post(route('room-rates.store'), datosDeTarifa($this->roomType, ['valid_to' => '2026-10-01', 'days_of_week' => ['8'], 'amount' => '0']))
        ->assertSessionHasErrors(['valid_to', 'days_of_week.0', 'amount']);
});

it('creates and renames cash registers with names unique per branch', function () {
    CashRegister::factory()->for($this->branch)->create(['name' => 'Caja recepción']);
    CashRegister::factory()->create(['name' => 'Caja bar']);

    $this->actingAs($this->admin)->get(route('cash-registers.create'))->assertOk();
    $this->actingAs($this->admin)->post(route('cash-registers.store'), ['name' => 'Caja recepción', 'is_active' => '1'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->post(route('cash-registers.store'), ['name' => 'Caja bar', 'is_active' => '1'])->assertRedirect(route('cash-registers.index'));

    $cashRegister = CashRegister::query()->whereBelongsTo($this->branch)->where('name', 'Caja bar')->sole();
    expect($cashRegister->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('cash-registers.update', $cashRegister), ['name' => 'Caja bar', 'is_active' => '0'])
        ->assertRedirect(route('cash-registers.index'));

    expect($cashRegister->refresh()->is_active)->toBeFalse();
    $this->actingAs($this->admin)->get(route('cash-registers.index'))->assertSee('Cajas inactivas')->assertSee(route('cash-registers.edit', $cashRegister));
});

it('does not deactivate a register with an open session', function () {
    $cashRegister = CashRegister::factory()->for($this->branch)->create(['name' => 'Caja recepción']);
    CashSession::factory()->for($cashRegister)->create();

    $this->actingAs($this->admin)
        ->put(route('cash-registers.update', $cashRegister), ['name' => 'Caja recepción', 'is_active' => '0'])
        ->assertSessionHasErrors('is_active');

    expect($cashRegister->refresh()->is_active)->toBeTrue();
});

it('hides register management from receptionists', function () {
    CashRegister::factory()->for($this->branch)->create();
    $receptionist = usuarioDeCatalogo($this->branch, BranchRole::Receptionist);

    $this->actingAs($receptionist)
        ->get(route('cash-registers.index'))
        ->assertOk()
        ->assertDontSee('Nueva caja')
        ->assertDontSee('Editar caja');
});
