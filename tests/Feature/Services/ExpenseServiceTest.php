<?php

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\CashService;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['expense_approval_threshold' => '500.00']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create(['opening_amount' => '1000.00']);
    $this->category = ExpenseCategory::factory()->create(['name' => 'Insumos de limpieza']);
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();
});

function registrarGasto(
    string $amount,
    PaymentMethod $paymentMethod = PaymentMethod::Cash,
    ?CashSession $cashSession = null,
    ?string $operationUuid = null,
): Expense {
    $test = test();

    return app(ExpenseService::class)->registrar(
        $test->branch,
        $test->clerk,
        $test->category,
        'Detergente y lavandina',
        $amount,
        $paymentMethod,
        $operationUuid ?? (string) Str::uuid(),
        $paymentMethod === PaymentMethod::Cash ? ($cashSession ?? $test->cashSession) : null,
    );
}

function efectivoEsperado(CashSession $cashSession): string
{
    return (string) app(CashService::class)->calcularEfectivoEsperado($cashSession);
}

it('confirms a cash expense up to the threshold and takes it out of the register', function () {
    $expense = registrarGasto('500.00');

    expect($expense->status)->toBe(ExpenseStatus::Confirmed)
        ->and($expense->cash_session_id)->toBe($this->cashSession->id)
        ->and($expense->expense_date->toDateString())->toBe($this->branch->current_business_date->toDateString())
        ->and(efectivoEsperado($this->cashSession))->toBe('500.00');

    $movement = $expense->cashMovements()->sole();
    expect($movement->movement_type)->toBe(CashMovementType::Expense)
        ->and($movement->direction)->toBe(CashMovementDirection::Out)
        ->and($movement->operation_uuid)->toBe($expense->operation_uuid);
    $this->assertDatabaseHas('activity_log', ['event' => 'expense.registered', 'subject_id' => $expense->id]);
});

it('records a non-cash expense without touching the register', function () {
    $expense = registrarGasto('120.00', PaymentMethod::BankTransfer);

    expect($expense->status)->toBe(ExpenseStatus::Confirmed)
        ->and($expense->cash_session_id)->toBeNull()
        ->and($expense->cashMovements()->exists())->toBeFalse();
});

it('leaves an expense above the threshold pending without moving cash', function () {
    $expense = registrarGasto('500.01');

    expect($expense->status)->toBe(ExpenseStatus::PendingApproval)
        ->and($expense->cash_session_id)->toBeNull()
        ->and($expense->cashMovements()->exists())->toBeFalse()
        ->and(efectivoEsperado($this->cashSession))->toBe('1000.00');
});

it('falls back to the global threshold when the branch has none', function () {
    config(['lodging.expense_approval_threshold' => '100.00']);
    $this->branch->update(['expense_approval_threshold' => null]);

    expect(registrarGasto('100.01')->status)->toBe(ExpenseStatus::PendingApproval);
});

it('rejects a cash expense that cannot leave an open register of the branch', function (Closure $sessionFor, string $message) {
    expect(fn () => app(ExpenseService::class)->registrar(
        $this->branch, $this->clerk, $this->category, 'Detergente', '50.00', PaymentMethod::Cash, (string) Str::uuid(), $sessionFor($this->branch),
    ))->toThrow(BusinessRuleException::class, $message);

    expect(Expense::count())->toBe(0);
})->with([
    'without a session' => [fn (Branch $branch) => null, 'requiere una sesión de caja abierta'],
    'closed session' => [fn (Branch $branch) => CashSession::factory()->for(CashRegister::factory()->for($branch))->cerrada()->create(), 'no está abierta'],
    'another branch' => [fn (Branch $branch) => CashSession::factory()->create(), 'pertenece a otra sucursal'],
]);

it('does not hand out more cash than the register holds', function () {
    registrarGasto('400.00');

    expect(fn () => registrarGasto('400.00', cashSession: CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create(['opening_amount' => '300.00'])))
        ->toThrow(BusinessRuleException::class, 'El gasto supera el efectivo de la caja (Bs 300.00).');
});

it('does not register an expense in an inactive category', function () {
    $this->category->update(['is_active' => false]);

    registrarGasto('50.00');
})->throws(BusinessRuleException::class, 'La categoría de gasto está inactiva.');

it('returns the original expense when the same request is retried', function () {
    $operationUuid = (string) Str::uuid();
    $expense = registrarGasto('50.00', operationUuid: $operationUuid);

    expect(registrarGasto('50.00', operationUuid: $operationUuid)->id)->toBe($expense->id)
        ->and(Expense::count())->toBe(1)
        ->and(efectivoEsperado($this->cashSession))->toBe('950.00');

    expect(fn () => registrarGasto('60.00', operationUuid: $operationUuid))->toThrow(IdempotencyConflictException::class);
});

it('takes the cash of a pending expense out of the register chosen at approval', function () {
    $expense = registrarGasto('800.00');

    $approved = app(ExpenseService::class)->aprobar($expense, $this->approver, $this->cashSession);

    expect($approved->status)->toBe(ExpenseStatus::Confirmed)
        ->and($approved->approved_by)->toBe($this->approver->id)
        ->and($approved->cash_session_id)->toBe($this->cashSession->id)
        ->and(efectivoEsperado($this->cashSession))->toBe('200.00');
    $this->assertDatabaseHas('activity_log', ['event' => 'expense.approved', 'subject_id' => $expense->id]);
});

it('approves a non-cash expense without a register', function () {
    $expense = registrarGasto('800.00', PaymentMethod::Qr);

    expect(app(ExpenseService::class)->aprobar($expense, $this->approver)->status)->toBe(ExpenseStatus::Confirmed)
        ->and($expense->cashMovements()->exists())->toBeFalse();
});

it('does not let whoever registered an expense approve it', function () {
    app(ExpenseService::class)->aprobar(registrarGasto('800.00'), $this->clerk, $this->cashSession);
})->throws(BusinessRuleException::class, 'Quien registra un gasto no puede aprobarlo.');

it('does not approve a cash expense without a register or one already resolved', function () {
    $service = app(ExpenseService::class);
    $expense = registrarGasto('800.00');

    expect(fn () => $service->aprobar($expense, $this->approver))->toThrow(BusinessRuleException::class, 'requiere una sesión de caja abierta');

    $service->rechazar($expense, $this->approver, 'Pedir otra cotización');

    expect(fn () => $service->aprobar($expense, $this->approver, $this->cashSession))->toThrow(BusinessRuleException::class, 'ya no está pendiente');
});

it('rejects a pending expense with a reason', function () {
    $rejected = app(ExpenseService::class)->rechazar(registrarGasto('800.00'), $this->approver, 'Pedir otra cotización');

    expect($rejected->status)->toBe(ExpenseStatus::Rejected)
        ->and($rejected->resolved_by)->toBe($this->approver->id)
        ->and($rejected->resolution_notes)->toBe('Pedir otra cotización');
    $this->assertDatabaseHas('activity_log', ['event' => 'expense.rejected', 'subject_id' => $rejected->id]);
});

it('voids a cash expense putting the cash back into an open register', function () {
    $expense = registrarGasto('150.00');
    $otherSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create(['opening_amount' => '0.00']);

    $voided = app(ExpenseService::class)->anular($expense, $this->approver, 'Importe equivocado', $otherSession);

    expect($voided->status)->toBe(ExpenseStatus::Voided)
        ->and($voided->resolution_notes)->toBe('Importe equivocado')
        ->and(efectivoEsperado($this->cashSession))->toBe('850.00')
        ->and(efectivoEsperado($otherSession))->toBe('150.00')
        ->and($expense->cashMovements()->where('direction', CashMovementDirection::In)->sole()->cash_session_id)->toBe($otherSession->id);
    $this->assertDatabaseHas('activity_log', ['event' => 'expense.voided', 'subject_id' => $expense->id]);

    expect(fn () => app(ExpenseService::class)->anular($expense, $this->approver, 'Otra vez', $this->cashSession))
        ->toThrow(BusinessRuleException::class, 'Solo se pueden anular gastos confirmados.');
});

it('does not void a pending expense', function () {
    app(ExpenseService::class)->anular(registrarGasto('800.00', PaymentMethod::Card), $this->approver, 'Error');
})->throws(BusinessRuleException::class, 'Solo se pueden anular gastos confirmados.');
