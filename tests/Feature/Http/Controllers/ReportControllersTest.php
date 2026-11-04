<?php

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch)->state(['room_number' => '204']))->create();
    StayCharge::factory()->for($this->stay)->create(['total_amount' => '250.00', 'service_date' => '2026-11-03']);
    $this->receptionist = personalDeReportes($this->branch, BranchRole::Receptionist);
    $this->supervisor = personalDeReportes($this->branch, BranchRole::Supervisor);
    $this->admin = personalDeReportes($this->branch, BranchRole::Admin);
});

function personalDeReportes(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('shows the financial report of the current business date to a supervisor', function () {
    $this->actingAs($this->supervisor)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('value="2026-11-03"', false)
        ->assertSee('Total facturable')
        ->assertSee('Bs 250.00')
        ->assertSee('Saldos pendientes')
        ->assertSee('Hab. 204');

    $this->actingAs($this->supervisor)->get(route('occupancy.index'))->assertSee(route('reports.index'));
});

it('validates the range of business dates', function () {
    $this->actingAs($this->supervisor)
        ->get(route('reports.index', ['desde' => '2026-11-03', 'hasta' => '2026-11-01']))
        ->assertSessionHasErrors('hasta');
});

it('keeps reports away from the receptionist', function () {
    $this->actingAs($this->receptionist)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($this->receptionist)->get(route('occupancy.index'))->assertDontSee(route('reports.index'));
});

it('lets only the branch admin browse the audit log of the branch', function () {
    $payment = Payment::factory()->for($this->stay)->create();
    $audit = app(AuditService::class);
    $audit->registrar('cash', 'payment.collected', $payment, $this->receptionist, $this->branch->id, ['amount' => '250.00']);
    $audit->registrar('reception', 'stay.checked_in', $this->stay, $this->receptionist, $this->branch->id);
    $audit->registrar('cash', 'payment.collected', $payment, $this->receptionist, Branch::factory()->create()->id, ['amount' => '999.00']);

    $this->actingAs($this->supervisor)->get(route('audit-log.index'))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('audit-log.index'))
        ->assertOk()
        ->assertSee('payment.collected')
        ->assertSee('stay.checked_in')
        ->assertSee($this->receptionist->name)
        ->assertDontSee('999.00');

    $this->actingAs($this->admin)
        ->get(route('audit-log.index', ['registro' => 'reception']))
        ->assertSee('stay.checked_in')
        ->assertDontSee('payment.collected');

    $this->actingAs($this->admin)
        ->get(route('audit-log.index', ['evento' => 'payment']))
        ->assertSee('payment.collected')
        ->assertDontSee('stay.checked_in');
});
