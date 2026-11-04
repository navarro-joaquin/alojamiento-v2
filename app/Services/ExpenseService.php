<?php

namespace App\Services;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use BcMath\Number;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expenses of a branch. Up to the branch approval threshold an expense is
 * confirmed when registered; above it, it waits for someone else's approval.
 * A cash expense leaves an open register only once confirmed, and voiding it
 * puts the cash back through a compensating movement.
 *
 * Rows are locked in the canonical order: cash_sessions → expenses.
 * Registration is idempotent on its operation UUID. Authorization is the
 * caller's responsibility.
 */
class ExpenseService
{
    public function __construct(
        private CashService $cashService,
        private AuditService $auditService,
    ) {}

    /**
     * The session is only used for a cash expense confirmed on the spot.
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     */
    public function registrar(
        Branch $branch,
        User $user,
        ExpenseCategory $expenseCategory,
        string $description,
        string $amount,
        PaymentMethod $paymentMethod,
        string $operationUuid,
        ?CashSession $cashSession = null,
        ?string $reference = null,
    ): Expense {
        $amount = (new Number($amount))->round(2);

        if ($amount <= 0) {
            throw BusinessRuleException::importeNoPositivo();
        }

        $cashSession = $paymentMethod->afectaEfectivo() && ! $this->requiereAprobacion($branch, $amount) ? $cashSession : null;

        $requestHash = hash('sha256', json_encode([
            'branch_id' => $branch->id,
            'expense_category_id' => $expenseCategory->id,
            'cash_session_id' => $cashSession?->id,
            'payment_method' => $paymentMethod->value,
            'amount' => (string) $amount,
        ], JSON_THROW_ON_ERROR));

        $existingExpense = Expense::query()->where('operation_uuid', $operationUuid)->first();

        if ($existingExpense !== null) {
            return $this->resolverReintento($existingExpense, $requestHash, $operationUuid);
        }

        try {
            return DB::transaction(fn (): Expense => $this->registrarGasto(
                $branch, $user, $expenseCategory, $description, $amount, $paymentMethod, $operationUuid, $requestHash, $cashSession, $reference,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrentExpense = Expense::query()->where('operation_uuid', $operationUuid)->first()
                ?? throw $exception;

            return $this->resolverReintento($concurrentExpense, $requestHash, $operationUuid);
        }
    }

    /**
     * Approve a pending expense; a cash one leaves the given open session now.
     *
     * @throws BusinessRuleException
     */
    public function aprobar(Expense $expense, User $user, ?CashSession $cashSession = null): Expense
    {
        if ($expense->payment_method->afectaEfectivo() && $cashSession === null) {
            throw BusinessRuleException::gastoEnEfectivoSinSesion();
        }

        return DB::transaction(function () use ($expense, $user, $cashSession): Expense {
            $lockedSession = $expense->payment_method->afectaEfectivo() ? $this->cashService->bloquearSesionAbierta($cashSession) : null;
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($lockedExpense->status !== ExpenseStatus::PendingApproval) {
                throw BusinessRuleException::gastoNoPendiente();
            }

            if ($lockedExpense->created_by === $user->id) {
                throw BusinessRuleException::aprobacionDeGastoPorQuienLoRegistro();
            }

            if ($lockedSession !== null) {
                $this->registrarSalidaDeEfectivo($lockedExpense, $lockedSession, $user);
            }

            $lockedExpense->update([
                'status' => ExpenseStatus::Confirmed,
                'cash_session_id' => $lockedSession?->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->auditService->registrar('cash', 'expense.approved', $lockedExpense, $user, $lockedExpense->branch_id, [
                'amount' => $lockedExpense->amount,
                'cash_session_id' => $lockedSession?->id,
            ]);

            return $lockedExpense;
        });
    }

    /**
     * @throws BusinessRuleException
     */
    public function rechazar(Expense $expense, User $user, string $notes): Expense
    {
        return DB::transaction(function () use ($expense, $user, $notes): Expense {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($lockedExpense->status !== ExpenseStatus::PendingApproval) {
                throw BusinessRuleException::gastoNoPendiente();
            }

            $lockedExpense->update([
                'status' => ExpenseStatus::Rejected,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ]);

            $this->auditService->registrar('cash', 'expense.rejected', $lockedExpense, $user, $lockedExpense->branch_id, [
                'notes' => $notes,
            ]);

            return $lockedExpense;
        });
    }

    /**
     * Void a confirmed expense. The cash of a cash expense comes back into the
     * given open session, which need not be the one it left from.
     *
     * @throws BusinessRuleException
     */
    public function anular(Expense $expense, User $user, string $reason, ?CashSession $cashSession = null): Expense
    {
        if ($expense->payment_method->afectaEfectivo() && $cashSession === null) {
            throw BusinessRuleException::gastoEnEfectivoSinSesion();
        }

        return DB::transaction(function () use ($expense, $user, $reason, $cashSession): Expense {
            $lockedSession = $expense->payment_method->afectaEfectivo() ? $this->cashService->bloquearSesionAbierta($cashSession) : null;
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($lockedExpense->status !== ExpenseStatus::Confirmed) {
                throw BusinessRuleException::gastoNoConfirmado();
            }

            if ($lockedSession !== null) {
                $this->verificarSesionDeLaSucursal($lockedSession, $lockedExpense->branch_id);

                $lockedExpense->cashMovements()->create([
                    'cash_session_id' => $lockedSession->id,
                    'performed_by' => $user->id,
                    'movement_type' => CashMovementType::Expense,
                    'payment_method' => PaymentMethod::Cash,
                    'direction' => CashMovementDirection::In,
                    'amount' => $lockedExpense->amount,
                    'affects_cash' => true,
                    'operation_uuid' => (string) Str::uuid(),
                    'description' => "Anulación del gasto #{$lockedExpense->id}",
                    'occurred_at' => now(),
                ]);
            }

            $lockedExpense->update([
                'status' => ExpenseStatus::Voided,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
                'resolution_notes' => $reason,
            ]);

            $this->auditService->registrar('cash', 'expense.voided', $lockedExpense, $user, $lockedExpense->branch_id, [
                'amount' => $lockedExpense->amount,
                'reason' => $reason,
                'cash_session_id' => $lockedSession?->id,
            ]);

            return $lockedExpense;
        });
    }

    /**
     * Whether the amount goes above the branch threshold (or the global default).
     */
    public function requiereAprobacion(Branch $branch, Number $amount): bool
    {
        return $amount > new Number((string) ($branch->expense_approval_threshold ?? config('lodging.expense_approval_threshold')));
    }

    private function registrarGasto(
        Branch $branch,
        User $user,
        ExpenseCategory $expenseCategory,
        string $description,
        Number $amount,
        PaymentMethod $paymentMethod,
        string $operationUuid,
        string $requestHash,
        ?CashSession $cashSession,
        ?string $reference,
    ): Expense {
        $requiresApproval = $this->requiereAprobacion($branch, $amount);

        if ($paymentMethod->afectaEfectivo() && ! $requiresApproval && $cashSession === null) {
            throw BusinessRuleException::gastoEnEfectivoSinSesion();
        }

        $lockedSession = $cashSession !== null ? $this->cashService->bloquearSesionAbierta($cashSession) : null;

        if ($lockedSession !== null) {
            $this->verificarSesionDeLaSucursal($lockedSession, $branch->id);
        }

        $concurrentExpense = Expense::query()->where('operation_uuid', $operationUuid)->first();

        if ($concurrentExpense !== null) {
            return $this->resolverReintento($concurrentExpense, $requestHash, $operationUuid);
        }

        if (! $expenseCategory->is_active) {
            throw BusinessRuleException::categoriaDeGastoInactiva();
        }

        $expense = Expense::create([
            'branch_id' => $branch->id,
            'expense_category_id' => $expenseCategory->id,
            'created_by' => $user->id,
            'description' => $description,
            'amount' => (string) $amount,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'expense_date' => $branch->current_business_date,
            'status' => $requiresApproval ? ExpenseStatus::PendingApproval : ExpenseStatus::Confirmed,
            'operation_uuid' => $operationUuid,
            'request_hash' => $requestHash,
        ]);

        if ($lockedSession !== null) {
            $this->registrarSalidaDeEfectivo($expense, $lockedSession, $user);
            $expense->update(['cash_session_id' => $lockedSession->id]);
        }

        $this->auditService->registrar('cash', 'expense.registered', $expense, $user, $branch->id, [
            'expense_category_id' => $expenseCategory->id,
            'amount' => $expense->amount,
            'payment_method' => $paymentMethod->value,
            'status' => $expense->status->value,
            'cash_session_id' => $lockedSession?->id,
        ]);

        return $expense;
    }

    /**
     * The register cannot hand out more cash than it is expected to hold.
     *
     * @throws BusinessRuleException
     */
    private function registrarSalidaDeEfectivo(Expense $expense, CashSession $lockedSession, User $user): void
    {
        $this->verificarSesionDeLaSucursal($lockedSession, $expense->branch_id);

        $expectedCash = $this->cashService->calcularEfectivoEsperado($lockedSession);

        if (new Number($expense->amount) > $expectedCash) {
            throw BusinessRuleException::efectivoInsuficiente($expectedCash);
        }

        $expense->cashMovements()->create([
            'cash_session_id' => $lockedSession->id,
            'performed_by' => $user->id,
            'movement_type' => CashMovementType::Expense,
            'payment_method' => PaymentMethod::Cash,
            'direction' => CashMovementDirection::Out,
            'amount' => $expense->amount,
            'affects_cash' => true,
            'operation_uuid' => $expense->operation_uuid,
            'description' => Str::limit("Gasto #{$expense->id}: {$expense->description}", 250),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @throws BusinessRuleException
     */
    private function verificarSesionDeLaSucursal(CashSession $lockedSession, int $branchId): void
    {
        if ($lockedSession->cashRegister->branch_id !== $branchId) {
            throw BusinessRuleException::sesionDeOtraSucursal();
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function resolverReintento(Expense $existingExpense, string $requestHash, string $operationUuid): Expense
    {
        if (! hash_equals($existingExpense->request_hash, $requestHash)) {
            throw IdempotencyConflictException::paraOperacion($operationUuid);
        }

        return $existingExpense;
    }
}
