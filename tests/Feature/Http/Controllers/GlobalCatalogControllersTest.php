<?php

use App\Enums\BranchRole;
use App\Http\Middleware\EnsureActiveBranch;
use App\Models\Branch;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdministrador()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeSucursal(array $overrides = []): array
{
    return [
        'code' => 'nor',
        'name' => 'Sucursal Norte',
        'timezone' => 'America/La_Paz',
        'check_in_time' => '13:00',
        'check_out_time' => '11:00',
        'cash_difference_threshold' => '20.00',
        'is_active' => '1',
        ...$overrides,
    ];
}

it('sends a super admin without branches to create the first one', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('occupancy.index'))
        ->assertRedirect(route('branches.create'));

    $this->actingAs($this->superAdmin)->get(route('branches.create'))->assertOk();
});

it('lets a super admin work in every active branch', function () {
    Branch::factory()->create(['name' => 'Sucursal Única']);

    $this->actingAs($this->superAdmin)
        ->get(route('occupancy.index'))
        ->assertOk()
        ->assertSee('Sucursal Única')
        ->assertSee('Administración');
});

it('creates a branch with today as business date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 01:00', 'UTC'));

    $this->actingAs($this->superAdmin)
        ->post(route('branches.store'), datosDeSucursal())
        ->assertRedirect(route('branches.index'));

    $branch = Branch::query()->sole();
    expect($branch->code)->toBe('NOR')
        ->and($branch->current_business_date->toDateString())->toBe('2026-11-03')
        ->and($branch->check_out_time)->toStartWith('11:00')
        ->and($branch->cash_difference_threshold)->toBe('20.00');
    $this->assertDatabaseHas('activity_log', ['event' => 'branch.created', 'subject_id' => $branch->id]);
});

it('updates a branch without touching its business date', function () {
    $branch = Branch::factory()->create(['code' => 'NOR', 'current_business_date' => '2026-10-01']);

    $this->actingAs($this->superAdmin)
        ->put(route('branches.update', $branch), datosDeSucursal(['name' => 'Norte renovada', 'is_active' => '0', 'current_business_date' => '2030-01-01']))
        ->assertRedirect(route('branches.index'));

    $branch->refresh();
    expect($branch->name)->toBe('Norte renovada')
        ->and($branch->is_active)->toBeFalse()
        ->and($branch->current_business_date->toDateString())->toBe('2026-10-01');
});

it('rejects a duplicated branch code and an unknown timezone', function () {
    Branch::factory()->create(['code' => 'NOR']);

    $this->actingAs($this->superAdmin)
        ->post(route('branches.store'), datosDeSucursal(['timezone' => 'Marte/Olympus']))
        ->assertSessionHasErrors(['code', 'timezone']);
});

it('creates and updates room types', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('room-types.store'), ['name' => 'Suite', 'default_capacity' => 2])
        ->assertRedirect(route('room-types.index'));

    $roomType = RoomType::query()->sole();

    $this->actingAs($this->superAdmin)
        ->put(route('room-types.update', $roomType), ['name' => 'Suite presidencial', 'default_capacity' => 3])
        ->assertRedirect(route('room-types.index'));

    expect($roomType->fresh()->only(['name', 'default_capacity']))->toBe(['name' => 'Suite presidencial', 'default_capacity' => 3]);
});

it('lists room types with their number of rooms', function () {
    Room::factory()->count(2)->for(RoomType::factory()->create(['name' => 'Doble']))->create();

    $this->actingAs($this->superAdmin)
        ->get(route('room-types.index'))
        ->assertOk()
        ->assertSeeInOrder(['Doble', '2']);
});

it('keeps global catalogs away from branch admins', function () {
    $branch = Branch::factory()->create();
    $branchAdmin = User::factory()->create();
    $branchAdmin->asignarRolEnSucursal(BranchRole::Admin, $branch->id);

    $this->actingAs($branchAdmin)->get(route('branches.index'))->assertForbidden();
    $this->actingAs($branchAdmin)->post(route('branches.store'), datosDeSucursal())->assertForbidden();
    $this->actingAs($branchAdmin)->put(route('branches.update', $branch), datosDeSucursal())->assertForbidden();
    $this->actingAs($branchAdmin)->post(route('room-types.store'), ['name' => 'Suite', 'default_capacity' => 2])->assertForbidden();
    $this->actingAs($branchAdmin)->get(route('occupancy.index'))->assertDontSee('Administración');
});

it('denies everything to a disabled super admin', function () {
    $disabledSuperAdmin = User::factory()->superAdministrador()->inactivo()->create();

    expect($disabledSuperAdmin->can('gestionar', Branch::class))->toBeFalse();
});

it('keeps the branch links of the menu on the global catalogs once a branch is chosen', function () {
    $branch = Branch::factory()->create(['name' => 'Sucursal Centro']);

    $this->actingAs($this->superAdmin)
        ->get(route('room-types.index'))
        ->assertOk()
        ->assertDontSee(route('cash-registers.index'));

    $this->actingAs($this->superAdmin)
        ->withSession([EnsureActiveBranch::SESSION_KEY => $branch->id])
        ->get(route('room-types.index'))
        ->assertOk()
        ->assertSee('Sucursal Centro')
        ->assertSee(route('cash-registers.index'))
        ->assertSee(route('rooms.index'));
});

it('does not share a branch the user can no longer work in', function () {
    $inactiveBranch = Branch::factory()->inactiva()->create(['name' => 'Sucursal Cerrada']);

    $this->actingAs($this->superAdmin)
        ->withSession([EnsureActiveBranch::SESSION_KEY => $inactiveBranch->id])
        ->get(route('room-types.index'))
        ->assertOk()
        ->assertDontSee(route('cash-registers.index'));
});
