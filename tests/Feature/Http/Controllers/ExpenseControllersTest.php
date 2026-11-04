<?php

use App\Enums\BranchRole;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['expense_approval_threshold' => '500.00']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch)->state(['name' => 'Caja recepción']))->create(['opening_amount' => '1000.00']);
    $this->category = ExpenseCategory::factory()->create(['name' => 'Insumos de limpieza']);
    $this->receptionist = personalDeGastos($this->branch, BranchRole::Receptionist);
    $this->supervisor = personalDeGastos($this->branch, BranchRole::Supervisor);
});

function personalDeGastos(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function datosDeGasto(array $overrides = []): array
{
    return [
        'expense_category_id' => test()->category->id,
        'description' => 'Detergente y lavandina',
        'amount' => '80.00',
        'payment_method' => PaymentMethod::Cash->value,
        'cash_session_id' => test()->cashSession->id,
        'reference' => 'Nota 1234',
        'operation_uuid' => (string) Str::uuid(),
        ...$overrides,
    ];
}

it('registers a cash expense from the expenses page', function () {
    $this->actingAs($this->receptionist)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Registrar gasto')
        ->assertSee('Insumos de limpieza')
        ->assertSee('Caja recepción')
        ->assertSee('Hasta Bs 500.00');

    $this->actingAs($this->receptionist)
        ->post(route('expenses.store'), datosDeGasto())
        ->assertRedirect(route('expenses.index'))
        ->assertSessionHas('status', 'Gasto de Bs 80.00 registrado.');

    $expense = Expense::query()->sole();
    expect($expense->status)->toBe(ExpenseStatus::Confirmed)
        ->and($expense->created_by)->toBe($this->receptionist->id)
        ->and($expense->reference)->toBe('Nota 1234');

    $this->actingAs($this->receptionist)
        ->get(route('cash-sessions.show', $this->cashSession))
        ->assertSee("Gasto #{$expense->id}: Detergente y lavandina")
        ->assertSee('Bs 920.00');
});

it('registers a double submission only once', function () {
    $data = datosDeGasto();

    $this->actingAs($this->receptionist)->post(route('expenses.store'), $data)->assertRedirect(route('expenses.index'));
    $this->actingAs($this->receptionist)->post(route('expenses.store'), $data)->assertRedirect(route('expenses.index'));

    expect(Expense::count())->toBe(1);
});

it('runs an expense above the threshold through approval', function () {
    $this->actingAs($this->receptionist)
        ->post(route('expenses.store'), datosDeGasto(['amount' => '800.00']))
        ->assertSessionHas('status', 'Gasto de Bs 800.00 registrado: supera el umbral de la sucursal y queda pendiente de aprobación.');

    $expense = Expense::query()->sole();

    $this->actingAs($this->receptionist)
        ->get(route('expenses.index'))
        ->assertSee('Bs 800.00')
        ->assertDontSee(route('expenses.approve', $expense));
    $this->actingAs($this->receptionist)
        ->post(route('expenses.approve', $expense), ['approval_cash_session_id' => $this->cashSession->id])
        ->assertForbidden();

    $this->actingAs($this->supervisor)
        ->get(route('expenses.index'))
        ->assertSee(route('expenses.approve', $expense))
        ->assertSee(route('expenses.reject', $expense));

    $this->actingAs($this->supervisor)
        ->post(route('expenses.approve', $expense), ['approval_cash_session_id' => $this->cashSession->id])
        ->assertRedirect(route('expenses.index'))
        ->assertSessionHas('status', 'Gasto de Bs 800.00 aprobado.');

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Confirmed)
        ->and($expense->cash_session_id)->toBe($this->cashSession->id);
});

it('does not offer a supervisor the approval of their own expense', function () {
    $expense = Expense::factory()->for($this->branch)->for($this->category)->pendiente()->create(['created_by' => $this->supervisor->id]);

    $this->actingAs($this->supervisor)
        ->get(route('expenses.index'))
        ->assertDontSee(route('expenses.approve', $expense))
        ->assertSee(route('expenses.reject', $expense));

    $this->actingAs($this->supervisor)->post(route('expenses.approve', $expense))->assertForbidden();
});

it('rejects a pending expense with a reason', function () {
    $expense = Expense::factory()->for($this->branch)->for($this->category)->pendiente()->create(['created_by' => $this->receptionist->id]);

    $this->actingAs($this->supervisor)
        ->post(route('expenses.reject', $expense), ['rejection_notes' => ''])
        ->assertSessionHasErrors('rejection_notes');

    $this->actingAs($this->supervisor)
        ->post(route('expenses.reject', $expense), ['rejection_notes' => 'Pedir otra cotización'])
        ->assertSessionHas('status', 'Gasto rechazado.');

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Rejected);
});

it('lets only a supervisor void an expense', function () {
    $this->actingAs($this->receptionist)->post(route('expenses.store'), datosDeGasto());
    $expense = Expense::query()->sole();

    $this->actingAs($this->receptionist)
        ->get(route('expenses.index'))
        ->assertDontSee(route('expenses.void', $expense));
    $this->actingAs($this->receptionist)
        ->post(route('expenses.void', $expense), ['void_reason' => 'Error', 'void_cash_session_id' => $this->cashSession->id])
        ->assertForbidden();

    $this->actingAs($this->supervisor)
        ->post(route('expenses.void', $expense), ['void_reason' => 'Importe equivocado', 'void_cash_session_id' => $this->cashSession->id])
        ->assertSessionHas('status', 'Gasto de Bs 80.00 anulado.');

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Voided);
});

it('shows a business rule violation as a form error', function () {
    $this->actingAs($this->receptionist)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), datosDeGasto(['cash_session_id' => null]))
        ->assertRedirect(route('expenses.index'))
        ->assertSessionHasErrors(['operation' => 'Un gasto en efectivo requiere una sesión de caja abierta.']);

    $this->actingAs($this->receptionist)
        ->post(route('expenses.store'), datosDeGasto(['expense_category_id' => ExpenseCategory::factory()->inactiva()->create()->id]))
        ->assertSessionHasErrors('expense_category_id');

    expect(Expense::count())->toBe(0);
});

it('keeps expenses away from housekeeping', function () {
    $housekeeper = personalDeGastos($this->branch, BranchRole::Housekeeping);

    $this->actingAs($housekeeper)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($housekeeper)->post(route('expenses.store'), datosDeGasto())->assertForbidden();
    $this->actingAs($housekeeper)->get(route('occupancy.index'))->assertDontSee(route('expenses.index'));
    $this->actingAs($this->receptionist)->get(route('occupancy.index'))->assertSee(route('expenses.index'));
});

it('does not touch expenses of another branch', function () {
    $expense = Expense::factory()->pendiente()->create();

    $this->actingAs($this->supervisor)->post(route('expenses.approve', $expense))->assertNotFound();
    $this->actingAs($this->supervisor)->post(route('expenses.reject', $expense), ['rejection_notes' => 'No'])->assertNotFound();
    $this->actingAs($this->supervisor)->get(route('expenses.index'))->assertDontSee(route('expenses.reject', $expense));
});

it('lets only a super admin manage expense categories', function () {
    $branchAdmin = personalDeGastos($this->branch, BranchRole::Admin);
    $superAdmin = User::factory()->superAdministrador()->create();

    $this->actingAs($branchAdmin)->get(route('expense-categories.index'))->assertForbidden();
    $this->actingAs($branchAdmin)->post(route('expense-categories.store'), ['name' => 'Publicidad'])->assertForbidden();

    $this->actingAs($superAdmin)->get(route('expense-categories.index'))->assertOk()->assertSee('Insumos de limpieza');
    $this->actingAs($superAdmin)
        ->post(route('expense-categories.store'), ['name' => 'Publicidad', 'is_active' => '1'])
        ->assertRedirect(route('expense-categories.index'));
    $this->actingAs($superAdmin)
        ->post(route('expense-categories.store'), ['name' => 'Publicidad', 'is_active' => '1'])
        ->assertSessionHasErrors('name');

    $this->actingAs($superAdmin)
        ->put(route('expense-categories.update', $this->category), ['name' => 'Limpieza', 'is_active' => '0'])
        ->assertRedirect(route('expense-categories.index'));

    expect($this->category->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->receptionist)->get(route('expenses.index'))->assertDontSee('>Limpieza<', false)->assertSee('Publicidad');
});
