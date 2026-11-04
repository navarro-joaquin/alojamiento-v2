<?php

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\CashService;
use App\Services\FolioBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * An active stay that owes one night (Bs 250.00).
 */
function crearEstadiaConDeuda(?Branch $branch = null): Stay
{
    $room = Room::factory()->for($branch ?? Branch::factory())->create();
    $stay = Stay::factory()->for($room)->create(['rate_amount' => '250.00']);
    StayCharge::factory()->for($stay)->create(['total_amount' => '250.00']);

    return $stay;
}

function crearSesionAbiertaEn(Branch $branch): CashSession
{
    return CashSession::factory()
        ->for(CashRegister::factory()->for($branch))
        ->create(['opening_amount' => '200.00']);
}

/**
 * An active stay without charges yet, so whatever it pays is credit.
 */
function crearEstadiaConSaldoAFavor(?Branch $branch = null): Stay
{
    $room = Room::factory()->for($branch ?? Branch::factory())->create();

    return Stay::factory()->for($room)->create(['rate_amount' => '250.00']);
}

function cobrarEfectivo(Stay $stay, CashSession $cashSession, string $amount, ?string $operationUuid = null, PaymentType $paymentType = PaymentType::Settlement): Payment
{
    return app(CashService::class)->cobrarPago(
        $stay->folio,
        $cashSession,
        User::factory()->create(),
        PaymentMethod::Cash,
        $paymentType,
        $amount,
        $operationUuid ?? (string) Str::uuid(),
    );
}

describe('abrirSesion', function () {
    it('opens a session on the branch business date', function () {
        $branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);
        $cashRegister = CashRegister::factory()->for($branch)->create();
        $user = User::factory()->create();

        $cashSession = app(CashService::class)->abrirSesion($cashRegister, $user, '150.00');

        expect($cashSession->status)->toBe(CashSessionStatus::Open)
            ->and($cashSession->business_date->toDateString())->toBe('2026-11-03')
            ->and($cashSession->opening_amount)->toBe('150.00')
            ->and($cashSession->opened_by)->toBe($user->id);
        $this->assertDatabaseHas('activity_log', ['event' => 'cash_session.opened', 'subject_id' => $cashSession->id]);
    });

    it('rejects a register that already has an open session', function () {
        $cashSession = CashSession::factory()->create();

        app(CashService::class)->abrirSesion($cashSession->cashRegister, User::factory()->create(), '0.00');
    })->throws(BusinessRuleException::class, 'ya tiene una sesión abierta');

    it('rejects an inactive register', function () {
        $cashRegister = CashRegister::factory()->create(['is_active' => false]);

        app(CashService::class)->abrirSesion($cashRegister, User::factory()->create(), '0.00');
    })->throws(BusinessRuleException::class, 'La caja está inactiva');

    it('rejects a negative opening amount', function () {
        app(CashService::class)->abrirSesion(CashRegister::factory()->create(), User::factory()->create(), '-1.00');
    })->throws(BusinessRuleException::class, 'no puede ser negativo');
});

describe('cobrarPago', function () {
    it('records the payment and the incoming cash movement', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);

        $payment = cobrarEfectivo($stay, $cashSession, '250.00');

        $cashMovement = $payment->cashMovements()->sole();
        expect($payment->amount)->toBe('250.00')
            ->and($cashMovement->movement_type)->toBe(CashMovementType::Payment)
            ->and($cashMovement->direction)->toBe(CashMovementDirection::In)
            ->and($cashMovement->affects_cash)->toBeTrue()
            ->and($cashMovement->operation_uuid)->toBe($payment->operation_uuid);
        $this->assertDatabaseHas('activity_log', ['event' => 'payment.collected', 'subject_id' => $payment->id]);
    });

    it('records a card payment without moving physical cash', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);

        $payment = app(CashService::class)->cobrarPago(
            $stay->folio, $cashSession, User::factory()->create(), PaymentMethod::Card, PaymentType::Settlement, '250.00', (string) Str::uuid(),
        );

        expect($payment->cashMovements()->sole()->affects_cash)->toBeFalse();
    });

    it('rejects a settlement above the outstanding balance', function () {
        $stay = crearEstadiaConDeuda();

        cobrarEfectivo($stay, crearSesionAbiertaEn($stay->branch), '250.01');
    })->throws(BusinessRuleException::class, 'supera el saldo pendiente');

    it('accepts a deposit up to the branch limit of nights at the agreed rate', function () {
        $stay = crearEstadiaConDeuda(Branch::factory()->create(['deposit_limit_nights' => 2]));

        $payment = app(CashService::class)->cobrarPago(
            $stay->folio, crearSesionAbiertaEn($stay->branch), User::factory()->create(), PaymentMethod::Cash, PaymentType::Deposit, '750.00', (string) Str::uuid(),
        );

        expect($payment->payment_type)->toBe(PaymentType::Deposit);
    });

    it('rejects a deposit above the branch limit', function () {
        $stay = crearEstadiaConDeuda(Branch::factory()->create(['deposit_limit_nights' => 2]));

        app(CashService::class)->cobrarPago(
            $stay->folio, crearSesionAbiertaEn($stay->branch), User::factory()->create(), PaymentMethod::Cash, PaymentType::Deposit, '750.01', (string) Str::uuid(),
        );
    })->throws(BusinessRuleException::class, 'supera el máximo permitido');

    it('falls back to the global deposit limit when the branch has none', function () {
        config(['lodging.deposit_limit_nights' => 1]);
        $stay = crearEstadiaConDeuda();

        app(CashService::class)->cobrarPago(
            $stay->folio, crearSesionAbiertaEn($stay->branch), User::factory()->create(), PaymentMethod::Cash, PaymentType::Deposit, '500.01', (string) Str::uuid(),
        );
    })->throws(BusinessRuleException::class, 'supera el máximo permitido');

    it('rejects a session that is not open', function (string $sessionState) {
        $stay = crearEstadiaConDeuda();
        $cashSession = CashSession::factory()
            ->for(CashRegister::factory()->for($stay->branch))
            ->{$sessionState}()
            ->create();

        cobrarEfectivo($stay, $cashSession, '100.00');
    })->with(['closing' => 'enCierre', 'closed' => 'cerrada'])
        ->throws(BusinessRuleException::class, 'no está abierta');

    it('rejects a session of another branch', function () {
        $stay = crearEstadiaConDeuda();

        cobrarEfectivo($stay, crearSesionAbiertaEn(Branch::factory()->create()), '100.00');
    })->throws(BusinessRuleException::class, 'pertenece a otra sucursal');

    it('rejects a cancelled stay', function () {
        $stay = Stay::factory()->create(['status' => StayStatus::Cancelled]);

        cobrarEfectivo($stay, crearSesionAbiertaEn($stay->branch), '100.00');
    })->throws(BusinessRuleException::class, 'no está activa');

    it('collects the debt left by a completed stay', function () {
        $stay = crearEstadiaConDeuda();
        $stay->update(['status' => StayStatus::Completed, 'check_out_at' => now()]);
        $cashSession = crearSesionAbiertaEn($stay->branch);

        expect(fn () => cobrarEfectivo($stay, $cashSession, '50.00', paymentType: PaymentType::Deposit))
            ->toThrow(BusinessRuleException::class, 'Una estadía finalizada solo admite el cobro de su saldo pendiente, no anticipos.');
        expect(fn () => cobrarEfectivo($stay, $cashSession, '250.01'))
            ->toThrow(BusinessRuleException::class, 'El pago supera el saldo pendiente (Bs 250.00).');

        cobrarEfectivo($stay, $cashSession, '250.00');

        expect((string) app(FolioBalanceService::class)->calcular($stay->folio))->toBe('0.00');
    });

    it('rejects an amount that is not positive', function () {
        $stay = crearEstadiaConDeuda();

        cobrarEfectivo($stay, crearSesionAbiertaEn($stay->branch), '0.00');
    })->throws(BusinessRuleException::class, 'mayor que cero');

    it('returns the original payment when the same request is retried', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $operationUuid = (string) Str::uuid();

        $firstPayment = cobrarEfectivo($stay, $cashSession, '100.00', $operationUuid);
        $retriedPayment = cobrarEfectivo($stay, $cashSession, '100.00', $operationUuid);

        expect($retriedPayment->is($firstPayment))->toBeTrue()
            ->and(Payment::count())->toBe(1)
            ->and(CashMovement::count())->toBe(1);
    });

    it('rejects a retry that reuses the operation with different data', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $operationUuid = (string) Str::uuid();
        cobrarEfectivo($stay, $cashSession, '100.00', $operationUuid);

        cobrarEfectivo($stay, $cashSession, '120.00', $operationUuid);
    })->throws(IdempotencyConflictException::class);
});

describe('devolverPago', function () {
    it('refunds cash from the open session', function () {
        $stay = crearEstadiaConSaldoAFavor();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $payment = cobrarEfectivo($stay, $cashSession, '250.00', paymentType: PaymentType::Deposit);

        $refund = app(CashService::class)->devolverPago(
            $payment, User::factory()->create(), '50.00', 'Cobro duplicado', (string) Str::uuid(), $cashSession,
        );

        $cashMovement = $refund->cashMovements()->sole();
        expect($refund->amount)->toBe('50.00')
            ->and($cashMovement->direction)->toBe(CashMovementDirection::Out)
            ->and($cashMovement->movement_type)->toBe(CashMovementType::Refund);
        $this->assertDatabaseHas('activity_log', ['event' => 'payment.refunded', 'subject_id' => $refund->id]);
    });

    it('refunds a card payment without a session or cash movement', function () {
        $stay = crearEstadiaConSaldoAFavor();
        $payment = app(CashService::class)->cobrarPago(
            $stay->folio, crearSesionAbiertaEn($stay->branch), User::factory()->create(), PaymentMethod::Card, PaymentType::Deposit, '250.00', (string) Str::uuid(),
        );

        $refund = app(CashService::class)->devolverPago($payment, User::factory()->create(), '250.00', 'Cancelación', (string) Str::uuid());

        expect($refund->cash_session_id)->toBeNull()
            ->and($refund->cashMovements()->exists())->toBeFalse();
    });

    it('rejects refunds that together exceed the payment', function () {
        $stay = crearEstadiaConSaldoAFavor();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $payment = cobrarEfectivo($stay, $cashSession, '250.00', paymentType: PaymentType::Deposit);
        app(CashService::class)->devolverPago($payment, User::factory()->create(), '200.00', 'Ajuste', (string) Str::uuid(), $cashSession);

        app(CashService::class)->devolverPago($payment, User::factory()->create(), '50.01', 'Ajuste', (string) Str::uuid(), $cashSession);
    })->throws(BusinessRuleException::class, 'supera el importe reembolsable (Bs 50.00)');

    it('rejects a cash refund without a session', function () {
        $stay = crearEstadiaConSaldoAFavor();
        $payment = cobrarEfectivo($stay, crearSesionAbiertaEn($stay->branch), '250.00', paymentType: PaymentType::Deposit);

        app(CashService::class)->devolverPago($payment, User::factory()->create(), '50.00', 'Ajuste', (string) Str::uuid());
    })->throws(BusinessRuleException::class, 'requiere una sesión de caja abierta');

    it('returns the original refund when the same request is retried', function () {
        $stay = crearEstadiaConSaldoAFavor();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $payment = cobrarEfectivo($stay, $cashSession, '250.00', paymentType: PaymentType::Deposit);
        $operationUuid = (string) Str::uuid();
        $user = User::factory()->create();

        $firstRefund = app(CashService::class)->devolverPago($payment, $user, '50.00', 'Ajuste', $operationUuid, $cashSession);
        $retriedRefund = app(CashService::class)->devolverPago($payment, $user, '50.00', 'Ajuste', $operationUuid, $cashSession);

        expect($retriedRefund->is($firstRefund))->toBeTrue()
            ->and($payment->refunds()->count())->toBe(1);
    });

    it('refunds only the credit of the stay', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $payment = cobrarEfectivo($stay, $cashSession, '250.00', paymentType: PaymentType::Deposit);
        cobrarEfectivo($stay, $cashSession, '30.00', paymentType: PaymentType::Deposit);

        expect(fn () => app(CashService::class)->devolverPago($payment, User::factory()->create(), '30.01', 'Ajuste', (string) Str::uuid(), $cashSession))
            ->toThrow(BusinessRuleException::class, 'supera el saldo a favor del huésped (Bs 30.00)');

        app(CashService::class)->devolverPago($payment, User::factory()->create(), '30.00', 'Ajuste', (string) Str::uuid(), $cashSession);

        expect((string) app(FolioBalanceService::class)->calcular($stay->folio))->toBe('0.00');
    });

    it('does not refund a settled stay', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $payment = cobrarEfectivo($stay, $cashSession, '250.00');

        app(CashService::class)->devolverPago($payment, User::factory()->create(), '0.01', 'Ajuste', (string) Str::uuid(), $cashSession);
    })->throws(BusinessRuleException::class, 'supera el saldo a favor del huésped (Bs 0.00)');
});

describe('cerrarSesion', function () {
    it('expects the opening amount plus cash in minus cash out, ignoring card payments', function () {
        $stay = crearEstadiaConDeuda();
        StayCharge::factory()->for($stay)->create(['service_date' => now()->addDay()->toDateString(), 'total_amount' => '250.00']);
        $cashSession = crearSesionAbiertaEn($stay->branch);
        $cashPayment = cobrarEfectivo($stay, $cashSession, '250.00');
        app(CashService::class)->cobrarPago(
            $stay->folio, $cashSession, User::factory()->create(), PaymentMethod::Card, PaymentType::Deposit, '280.00', (string) Str::uuid(),
        );
        app(CashService::class)->devolverPago($cashPayment, User::factory()->create(), '30.00', 'Ajuste', (string) Str::uuid(), $cashSession);

        $closedSession = app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '420.00');

        expect($closedSession->expected_amount)->toBe('420.00')
            ->and($closedSession->difference_amount)->toBe('0.00')
            ->and($closedSession->status)->toBe(CashSessionStatus::Closed);
    });

    it('sends the session to review when the difference exceeds the branch threshold', function () {
        $branch = Branch::factory()->create(['cash_difference_threshold' => '5.00']);
        $cashSession = crearSesionAbiertaEn($branch);

        $closedSession = app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '194.99');

        expect($closedSession->difference_amount)->toBe('-5.01')
            ->and($closedSession->status)->toBe(CashSessionStatus::PendingReview);
    });

    it('closes the session when the difference is exactly the threshold', function () {
        $branch = Branch::factory()->create(['cash_difference_threshold' => '5.00']);
        $cashSession = crearSesionAbiertaEn($branch);

        $closedSession = app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '205.00');

        expect($closedSession->status)->toBe(CashSessionStatus::Closed);
    });

    it('falls back to the global threshold when the branch has none', function () {
        config(['lodging.cash_difference_threshold' => '1.00']);
        $cashSession = crearSesionAbiertaEn(Branch::factory()->create());

        $closedSession = app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '201.01');

        expect($closedSession->status)->toBe(CashSessionStatus::PendingReview);
    });

    it('rejects a session that is already closed', function () {
        $cashSession = CashSession::factory()->cerrada()->create();

        app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '200.00');
    })->throws(BusinessRuleException::class, 'no está abierta');

    it('rejects new payments once the session is closed', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '200.00');

        cobrarEfectivo($stay, $cashSession, '100.00');
    })->throws(BusinessRuleException::class, 'no está abierta');

    it('leaves the movements of the session untouched', function () {
        $stay = crearEstadiaConDeuda();
        $cashSession = crearSesionAbiertaEn($stay->branch);
        cobrarEfectivo($stay, $cashSession, '250.00');
        $movementsBefore = DB::table('cash_movements')->get();

        app(CashService::class)->cerrarSesion($cashSession, User::factory()->create(), '450.00');

        expect(DB::table('cash_movements')->get())->toEqual($movementsBefore);
    });
});
