<?php

use App\Enums\BranchRole;
use App\Enums\CashCountType;
use App\Enums\CashSessionStatus;
use App\Models\Branch;
use App\Models\CashCount;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['cash_difference_threshold' => '10.00']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch)->state(['name' => 'Caja recepción']))->create(['opening_amount' => '200.00']);
    $this->receptionist = personalDeCaja($this->branch, BranchRole::Receptionist);
    $this->supervisor = personalDeCaja($this->branch, BranchRole::Supervisor);
});

function personalDeCaja(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('records a partial count from the session page', function () {
    $this->actingAs($this->receptionist)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertSee('Registrar arqueo')
        ->assertSee('Billete de Bs 200');

    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.counts.store', $this->cashSession), ['count_declared_amount' => '195.00', 'count_notes' => 'Cambio de turno'])
        ->assertRedirect(route('cash-sessions.show', $this->cashSession))
        ->assertSessionHas('status', 'Arqueo registrado: Bs 195.00 contados, diferencia Bs -5.00.');

    $this->actingAs($this->receptionist)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertSee('Arqueo parcial')
        ->assertSee('Cambio de turno');
});

it('takes the amount counted from the bills and coins when they are detailed', function () {
    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.counts.store', $this->cashSession), [
            'count_declared_amount' => '',
            'count_denominations' => ['10000' => '1', '5000' => '2', '50' => ''],
        ])
        ->assertSessionHasNoErrors();

    expect(CashCount::query()->sole()->declared_amount)->toBe('200.00');

    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.counts.store', $this->cashSession), ['count_declared_amount' => '', 'count_denominations' => ['10000' => '']])
        ->assertSessionHasErrors('count_declared_amount');
});

it('closes with the bills and coins detailed', function () {
    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.close', $this->cashSession), ['denominations' => ['20000' => '1']])
        ->assertSessionHas('status', 'Caja cerrada: Cerrada (diferencia Bs 0.00).');

    expect($this->cashSession->cashCounts()->sole()->count_type)->toBe(CashCountType::Closing);
});

it('runs a closing with a large difference through review', function () {
    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.close', $this->cashSession), ['declared_amount' => '150.00'])
        ->assertSessionHas('status', 'Caja cerrada: Pendiente de revisión (diferencia Bs -50.00).');

    $this->actingAs($this->receptionist)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertDontSee('Dar por revisada')
        ->assertSee('Pendiente de que la revise un supervisor');
    $this->actingAs($this->receptionist)
        ->post(route('cash-sessions.review', $this->cashSession), ['review_notes' => 'Revisado'])
        ->assertForbidden();
    $this->actingAs($this->receptionist)
        ->get(route('cash-registers.index'))
        ->assertDontSee('Cierres por revisar');

    $this->actingAs($this->supervisor)
        ->get(route('cash-registers.index'))
        ->assertSee('Cierres por revisar')
        ->assertSee('diferencia Bs -50.00');

    $this->actingAs($this->supervisor)
        ->post(route('cash-sessions.review', $this->cashSession), ['review_notes' => ''])
        ->assertSessionHasErrors('review_notes');

    $this->actingAs($this->supervisor)
        ->post(route('cash-sessions.review', $this->cashSession), ['review_notes' => 'Faltante repuesto por el cajero'])
        ->assertRedirect(route('cash-sessions.show', $this->cashSession))
        ->assertSessionHas('status', 'Cierre revisado: la sesión queda cerrada.');

    expect($this->cashSession->refresh()->status)->toBe(CashSessionStatus::Closed);

    $this->actingAs($this->supervisor)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertSee("Revisada por {$this->supervisor->name}", false)
        ->assertSee('Faltante repuesto por el cajero');
});

it('does not let a supervisor review their own closing', function () {
    $this->actingAs($this->supervisor)->post(route('cash-sessions.close', $this->cashSession), ['declared_amount' => '150.00']);

    $this->actingAs($this->supervisor)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertDontSee('Dar por revisada');
    $this->actingAs($this->supervisor)
        ->post(route('cash-sessions.review', $this->cashSession), ['review_notes' => 'Revisado'])
        ->assertForbidden();
});

it('keeps counts and reviews away from other branches and housekeeping', function () {
    $otherSession = CashSession::factory()->create();
    $housekeeper = personalDeCaja($this->branch, BranchRole::Housekeeping);

    $this->actingAs($this->supervisor)
        ->post(route('cash-sessions.counts.store', $otherSession), ['count_declared_amount' => '200.00'])
        ->assertNotFound();
    $this->actingAs($housekeeper)
        ->post(route('cash-sessions.counts.store', $this->cashSession), ['count_declared_amount' => '200.00'])
        ->assertForbidden();

    expect(CashCount::count())->toBe(0);
});
