<?php

use App\Enums\BranchRole;
use App\Enums\StayStatus;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\Stay;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);
    $this->room = Room::factory()->for($this->branch)->create(['room_number' => '201', 'capacity' => 2]);
    RoomRate::factory()->for($this->branch)->create(['room_type_id' => $this->room->room_type_id, 'amount' => '250.00', 'valid_from' => '2026-01-01']);
    $this->receptionist = User::factory()->create();
    $this->receptionist->asignarRolEnSucursal(BranchRole::Receptionist, $this->branch->id);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeIngreso(array $overrides = []): array
{
    return array_replace_recursive([
        'primary_guest' => [
            'document_type' => 'ci',
            'document_number' => ' 1234567 ',
            'document_complement' => '1a',
            'issuing_country' => 'bo',
            'first_name' => 'Ana',
            'last_name' => 'Quispe',
            'nationality' => 'BO',
        ],
        'companions' => [
            ['document_type' => 'ci', 'document_number' => null, 'issuing_country' => 'BO'],
        ],
        'adults' => 1,
        'children' => 0,
        'expected_check_out_date' => '2026-11-05',
    ], $overrides);
}

it('shows the check-in form for an available room', function () {
    $this->actingAs($this->receptionist)
        ->get(route('stays.create', $this->room))
        ->assertOk()
        ->assertSee('Huésped principal')
        ->assertSee('Acompañantes');
});

it('checks in a new guest and opens the stay page', function () {
    $response = $this->actingAs($this->receptionist)->post(route('stays.store', $this->room), datosDeIngreso());

    $stay = Stay::query()->sole();
    $response->assertRedirect(route('stays.show', $stay))->assertSessionHas('status', 'Ingreso registrado en la habitación 201.');

    $guest = Guest::query()->sole();
    expect($guest->document_number)->toBe('1234567')
        ->and($guest->issuing_country)->toBe('BO')
        ->and($stay->status)->toBe(StayStatus::Active)
        ->and($stay->expected_check_out_at->timezone($this->branch->timezone)->format('Y-m-d H:i'))->toBe('2026-11-05 12:00')
        ->and($stay->charges()->count())->toBe(1);
});

it('reuses a known guest found by document and updates the contact details', function () {
    $knownGuest = Guest::factory()->create([
        'document_type' => 'ci', 'document_number' => '1234567', 'document_complement' => '1A', 'issuing_country' => 'BO', 'phone' => null,
    ]);

    $this->actingAs($this->receptionist)
        ->post(route('stays.store', $this->room), datosDeIngreso(['primary_guest' => ['phone' => '70000000']]));

    expect(Guest::count())->toBe(1)
        ->and($knownGuest->fresh()->phone)->toBe('70000000')
        ->and(Stay::query()->sole()->guests()->sole()->is($knownGuest))->toBeTrue();
});

it('registers the companions that have a document', function () {
    $this->actingAs($this->receptionist)->post(route('stays.store', $this->room), datosDeIngreso([
        'adults' => 2,
        'companions' => [['document_number' => '7654321', 'first_name' => 'Luis', 'last_name' => 'Mamani']],
    ]));

    expect(Stay::query()->sole()->guests()->count())->toBe(2);
});

it('requires the name of a companion with a document', function () {
    $this->actingAs($this->receptionist)
        ->post(route('stays.store', $this->room), datosDeIngreso(['companions' => [['document_number' => '7654321']]]))
        ->assertSessionHasErrors(['companions.0.first_name', 'companions.0.last_name']);

    expect(Stay::count())->toBe(0);
});

it('shows a rejected check-in as a form error', function () {
    $this->room->update(['housekeeping_status' => 'dirty']);

    $this->actingAs($this->receptionist)
        ->from(route('stays.create', $this->room))
        ->post(route('stays.store', $this->room), datosDeIngreso())
        ->assertRedirect(route('stays.create', $this->room))
        ->assertSessionHasErrors(['operation' => 'La habitación no está disponible: debe estar en servicio y limpia.']);
});

it('forbids check-in to roles without the permission', function () {
    $housekeeper = User::factory()->create();
    $housekeeper->asignarRolEnSucursal(BranchRole::Housekeeping, $this->branch->id);

    $this->actingAs($housekeeper)->get(route('stays.create', $this->room))->assertForbidden();
    $this->actingAs($housekeeper)->post(route('stays.store', $this->room), datosDeIngreso())->assertForbidden();
});

it('shows the stay with its charges, payments and balance', function () {
    $this->actingAs($this->receptionist)->post(route('stays.store', $this->room), datosDeIngreso());

    $this->actingAs($this->receptionist)
        ->get(route('stays.show', Stay::query()->sole()))
        ->assertOk()
        ->assertSeeInOrder(['Ana Quispe', 'Saldo', 'Bs 250.00', 'Pendiente de pago', 'Alojamiento']);
});

it('hides stays of other branches', function () {
    $this->actingAs($this->receptionist)
        ->get(route('stays.show', Stay::factory()->create()))
        ->assertNotFound();
});
