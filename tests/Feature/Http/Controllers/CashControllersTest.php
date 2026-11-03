<?php

use App\Enums\BranchRole;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\CashService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->cashRegister = CashRegister::factory()->for($this->branch)->create(['name' => 'Caja recepción']);
});

function cajeroConRol(Branch $branch, BranchRole $role = BranchRole::Receptionist): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('lists the registers of the active branch', function () {
    CashRegister::factory()->create(['name' => 'Caja de otra sucursal']);

    $this->actingAs(cajeroConRol($this->branch))
        ->get(route('cash-registers.index'))
        ->assertOk()
        ->assertSee('Caja recepción')
        ->assertSee('Abrir caja')
        ->assertDontSee('Caja de otra sucursal');
});

it('hides the registers from roles that do not handle cash', function () {
    $housekeeper = cajeroConRol($this->branch, BranchRole::Housekeeping);

    $this->actingAs($housekeeper)->get(route('cash-registers.index'))->assertForbidden();
    $this->actingAs($housekeeper)->get(route('occupancy.index'))->assertDontSee(route('cash-registers.index'));
});

it('opens a session and shows it', function () {
    $response = $this->actingAs(cajeroConRol($this->branch))
        ->post(route('cash-sessions.store', $this->cashRegister), ['opening_amount' => '150.00']);

    $cashSession = CashSession::query()->sole();
    $response->assertRedirect(route('cash-sessions.show', $cashSession));

    expect($cashSession->opening_amount)->toBe('150.00');
});

it('shows a second opening of the same register as a form error', function () {
    CashSession::factory()->for($this->cashRegister)->create();

    $this->actingAs(cajeroConRol($this->branch))
        ->post(route('cash-sessions.store', $this->cashRegister), ['opening_amount' => '0'])
        ->assertSessionHasErrors(['operation' => 'La caja ya tiene una sesión abierta.']);
});

it('shows the expected cash and the net amount of each method', function () {
    $cashSession = CashSession::factory()->for($this->cashRegister)->create(['opening_amount' => '100.00']);
    $stay = Stay::factory()->for(Room::factory()->for($this->branch))->create();
    StayCharge::factory()->for($stay)->create(['total_amount' => '400.00']);
    $user = cajeroConRol($this->branch);
    app(CashService::class)->cobrarPago($stay, $cashSession, $user, PaymentMethod::Cash, PaymentType::Settlement, '250.00', (string) Str::uuid());
    app(CashService::class)->cobrarPago($stay, $cashSession, $user, PaymentMethod::Card, PaymentType::Settlement, '150.00', (string) Str::uuid());

    $this->actingAs($user)
        ->get(route('cash-sessions.show', $cashSession))
        ->assertOk()
        ->assertSeeInOrder(['Efectivo', 'Bs 250.00', 'Tarjeta', 'Bs 150.00'])
        ->assertViewHas('expectedCash', fn ($expectedCash) => (string) $expectedCash === '350.00');
});

it('closes the session with the counted cash', function () {
    $cashSession = CashSession::factory()->for($this->cashRegister)->create(['opening_amount' => '100.00']);

    $this->actingAs(cajeroConRol($this->branch))
        ->post(route('cash-sessions.close', $cashSession), ['declared_amount' => '95.00'])
        ->assertRedirect(route('cash-sessions.show', $cashSession))
        ->assertSessionHas('status', 'Caja cerrada: Cerrada (diferencia Bs -5.00).');

    expect($cashSession->fresh()->status)->toBe(CashSessionStatus::Closed);
});

it('forbids seeing a session of another branch', function () {
    $this->actingAs(cajeroConRol($this->branch, BranchRole::Admin))
        ->get(route('cash-sessions.show', CashSession::factory()->create()))
        ->assertForbidden();
});
