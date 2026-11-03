<?php

use App\Enums\BranchRole;
use App\Enums\CheckoutExceptionStatus;
use App\Enums\StayStatus;
use App\Models\Branch;
use App\Models\CheckoutException;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch)->state(['room_number' => '204']))->create();
    StayCharge::factory()->for($this->stay)->create(['total_amount' => '250.00']);
    $this->receptionist = personalDeSalida($this->branch, BranchRole::Receptionist);
    $this->supervisor = personalDeSalida($this->branch, BranchRole::Supervisor);
});

function personalDeSalida(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('runs the whole checkout with debt: request, approval and check-out', function () {
    $this->actingAs($this->receptionist)
        ->get(route('stays.show', $this->stay))
        ->assertSee('Solicitar autorización por Bs 250.00')
        ->assertSee('Registrar salida con deuda');

    $this->actingAs($this->receptionist)
        ->post(route('stays.checkout-exceptions.store', $this->stay), ['exception_reason' => 'Paga mañana por transferencia'])
        ->assertRedirect(route('stays.show', $this->stay))
        ->assertSessionHas('status', 'Salida con deuda de Bs 250.00 solicitada: falta que la autorice un supervisor.');

    $checkoutException = CheckoutException::query()->sole();

    $this->actingAs($this->supervisor)
        ->get(route('checkout-exceptions.index'))
        ->assertOk()
        ->assertSee('204')
        ->assertSee('Paga mañana por transferencia')
        ->assertSee(route('checkout-exceptions.approve', $checkoutException));

    $this->actingAs($this->supervisor)
        ->from(route('checkout-exceptions.index'))
        ->post(route('checkout-exceptions.approve', $checkoutException))
        ->assertRedirect(route('checkout-exceptions.index'))
        ->assertSessionHas('status');

    $this->actingAs($this->receptionist)
        ->get(route('stays.show', $this->stay))
        ->assertSee('Autorizada')
        ->assertDontSee('Solicitar autorización');

    $this->actingAs($this->receptionist)
        ->post(route('stays.check-out', $this->stay))
        ->assertRedirect(route('occupancy.index'));

    expect($this->stay->refresh()->status)->toBe(StayStatus::Completed)
        ->and($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Used);
});

it('keeps approval and the pending list away from the receptionist', function () {
    $checkoutException = CheckoutException::factory()->for($this->stay)->create(['requested_by' => $this->supervisor->id]);

    $this->actingAs($this->receptionist)->get(route('checkout-exceptions.index'))->assertForbidden();
    $this->actingAs($this->receptionist)->post(route('checkout-exceptions.approve', $checkoutException))->assertForbidden();
    $this->actingAs($this->receptionist)->post(route('checkout-exceptions.reject', $checkoutException), ['rejection_notes' => 'No'])->assertForbidden();
    $this->actingAs($this->receptionist)->get(route('occupancy.index'))->assertDontSee(route('checkout-exceptions.index'));

    expect($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Requested);
});

it('does not offer a supervisor the approval of their own request', function () {
    $checkoutException = CheckoutException::factory()->for($this->stay)->create(['requested_by' => $this->supervisor->id]);

    $this->actingAs($this->supervisor)
        ->get(route('stays.show', $this->stay))
        ->assertDontSee('Autorizar salida con deuda')
        ->assertSee('Rechazar');

    $this->actingAs($this->supervisor)->post(route('checkout-exceptions.approve', $checkoutException))->assertForbidden();
});

it('rejects a request with a reason', function () {
    $checkoutException = CheckoutException::factory()->for($this->stay)->create(['requested_by' => $this->receptionist->id]);

    $this->actingAs($this->supervisor)
        ->post(route('checkout-exceptions.reject', $checkoutException), ['rejection_notes' => ''])
        ->assertSessionHasErrors('rejection_notes');

    $this->actingAs($this->supervisor)
        ->from(route('stays.show', $this->stay))
        ->post(route('checkout-exceptions.reject', $checkoutException), ['rejection_notes' => 'Sin garantía'])
        ->assertRedirect(route('stays.show', $this->stay))
        ->assertSessionHas('status', 'Salida con deuda rechazada.');

    expect($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Rejected)
        ->and($checkoutException->resolved_by)->toBe($this->supervisor->id);
});

it('shows a check-out without a valid approval as a form error', function () {
    CheckoutException::factory()->for($this->stay)->vencida()->create(['requested_by' => $this->receptionist->id]);

    $this->actingAs($this->receptionist)
        ->get(route('stays.show', $this->stay))
        ->assertSee('Vencida')
        ->assertSee('Solicitar autorización por Bs 250.00');

    $this->actingAs($this->receptionist)
        ->from(route('stays.show', $this->stay))
        ->post(route('stays.check-out', $this->stay))
        ->assertSessionHasErrors(['operation' => 'La autorización de salida con deuda venció: solicite una nueva.']);

    expect($this->stay->refresh()->status)->toBe(StayStatus::Active);
});

it('does not touch exceptions of another branch', function () {
    $otherStay = Stay::factory()->create();
    StayCharge::factory()->for($otherStay)->create();
    $checkoutException = CheckoutException::factory()->for($otherStay)->create();

    $this->actingAs($this->supervisor)->post(route('checkout-exceptions.approve', $checkoutException))->assertNotFound();
    $this->actingAs($this->receptionist)->post(route('stays.checkout-exceptions.store', $otherStay), ['exception_reason' => 'X'])->assertNotFound();
    $this->actingAs($this->supervisor)->get(route('checkout-exceptions.index'))->assertDontSee(route('checkout-exceptions.approve', $checkoutException));
});
