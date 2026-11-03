<?php

use App\Enums\BranchRole;
use App\Enums\ChargeStatus;
use App\Enums\StayStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\StayBalanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch))->create(['rate_amount' => '250.00']);
    $this->night = StayCharge::factory()->for($this->stay)->create(['total_amount' => '250.00']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create();
});

function operadorConRol(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

function saldoDe(Stay $stay): string
{
    return (string) app(StayBalanceService::class)->calcular($stay);
}

it('adds a charge to the stay', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.charges.store', $this->stay), ['charge_type' => 'product', 'description' => 'Agua', 'quantity' => '2', 'unit_price' => '7.50'])
        ->assertRedirect(route('stays.show', $this->stay))
        ->assertSessionHas('status', 'Cargo registrado.');

    expect(saldoDe($this->stay))->toBe('265.00');
});

it('does not accept automatic charge types from the form', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.charges.store', $this->stay), ['charge_type' => 'lodging', 'description' => 'Noche', 'quantity' => '1', 'unit_price' => '250'])
        ->assertSessionHasErrors('charge_type');
});

it('applies a discount only with the discount permission', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.discounts.store', $this->stay), ['discount_description' => 'Cliente frecuente', 'discount_amount' => '50'])
        ->assertForbidden();

    $this->actingAs(operadorConRol($this->branch, BranchRole::Supervisor))
        ->post(route('stays.discounts.store', $this->stay), ['discount_description' => 'Cliente frecuente', 'discount_amount' => '50'])
        ->assertSessionHas('status', 'Descuento aplicado.');

    expect(saldoDe($this->stay))->toBe('200.00');
});

it('voids a charge with a reason', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Supervisor))
        ->post(route('stay-charges.void', $this->night), ['void_reason' => 'Noche duplicada'])
        ->assertRedirect(route('stays.show', $this->stay));

    expect($this->night->fresh()->status)->toBe(ChargeStatus::Voided)
        ->and(saldoDe($this->stay))->toBe('0.00');
});

it('collects a payment only once when the form is submitted twice', function () {
    $receptionist = operadorConRol($this->branch, BranchRole::Receptionist);
    $paymentForm = [
        'cash_session_id' => $this->cashSession->id,
        'payment_method' => 'cash',
        'payment_type' => 'settlement',
        'amount' => '250.00',
        'operation_uuid' => (string) Str::uuid(),
    ];

    $this->actingAs($receptionist)->post(route('stays.payments.store', $this->stay), $paymentForm)
        ->assertSessionHas('status', 'Cobro de Bs 250.00 registrado.');
    $this->actingAs($receptionist)->post(route('stays.payments.store', $this->stay), $paymentForm)
        ->assertSessionHas('status', 'Cobro de Bs 250.00 registrado.');

    expect(Payment::count())->toBe(1)
        ->and(saldoDe($this->stay))->toBe('0.00');
});

it('shows a payment above the balance as a form error', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.payments.store', $this->stay), [
            'cash_session_id' => $this->cashSession->id,
            'payment_method' => 'cash',
            'payment_type' => 'settlement',
            'amount' => '300.00',
            'operation_uuid' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors(['operation' => 'El pago supera el saldo pendiente (Bs 250.00).']);
});

it('checks out a settled stay', function () {
    Payment::factory()->for($this->stay)->create(['amount' => '250.00']);

    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.check-out', $this->stay))
        ->assertRedirect(route('occupancy.index'));

    expect($this->stay->fresh()->status)->toBe(StayStatus::Completed);
});

it('refunds the credit in cash at check-out from the chosen register', function () {
    Payment::factory()->for($this->stay)->anticipo()->create(['amount' => '300.00', 'cash_session_id' => $this->cashSession->id]);

    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.check-out', $this->stay), ['cash_session_id' => $this->cashSession->id])
        ->assertRedirect(route('occupancy.index'));

    expect($this->stay->fresh()->status)->toBe(StayStatus::Completed)
        ->and(saldoDe($this->stay))->toBe('0.00');
});

it('keeps a stay with debt active at check-out', function () {
    $this->actingAs(operadorConRol($this->branch, BranchRole::Receptionist))
        ->post(route('stays.check-out', $this->stay))
        ->assertSessionHasErrors('operation');

    expect($this->stay->fresh()->status)->toBe(StayStatus::Active);
});

it('moves the stay to another available room', function () {
    $newRoom = Room::factory()->for($this->branch)->create(['room_number' => '305']);
    RoomRate::factory()->for($this->branch)->create(['room_type_id' => $newRoom->room_type_id, 'amount' => '300.00', 'valid_from' => '2020-01-01']);
    $receptionist = operadorConRol($this->branch, BranchRole::Receptionist);

    $this->actingAs($receptionist)->get(route('stays.room-changes.create', $this->stay))->assertOk()->assertSee('305');

    $this->actingAs($receptionist)
        ->post(route('stays.room-changes.store', $this->stay), ['room_id' => $newRoom->id, 'reason' => 'Ruido'])
        ->assertRedirect(route('stays.show', $this->stay))
        ->assertSessionHas('status', 'Estadía trasladada a la habitación 305.');

    expect($this->stay->fresh()->room_id)->toBe($newRoom->id);
});

it('forbids operations on stays of another branch', function () {
    $otherStay = Stay::factory()->create();

    $this->actingAs(operadorConRol($this->branch, BranchRole::Admin))
        ->post(route('stays.charges.store', $otherStay), ['charge_type' => 'product', 'description' => 'Agua', 'quantity' => '1', 'unit_price' => '5'])
        ->assertForbidden();
});
