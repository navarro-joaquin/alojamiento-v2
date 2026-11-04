<?php

use App\Enums\ChargeType;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\ExpenseCategory;
use App\Models\Guest;
use App\Models\InventoryBalance;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReceiptSequence;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashService;
use App\Services\ChargeService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\NightAuditService;
use App\Services\ReceiptService;
use App\Services\ReceptionService;
use App\Services\StayBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Feature\Concurrency\ConcurrentRace;

/*
 * These tests commit their data so that other processes can see it: they
 * truncate the database instead of wrapping each test in a transaction, and
 * clean up afterwards so the tests that use RefreshDatabase start empty.
 */
uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

/**
 * @param  array{todasEsperaron: bool, resultados: array<string, array<string, mixed>>}  $race
 * @return array{exitosas: array<string, mixed>, fallidas: array<string, string>}
 */
function separarResultados(array $race): array
{
    expect($race['todasEsperaron'])->toBeTrue('Las operaciones no llegaron a esperar el bloqueo de la fila.');

    $results = collect($race['resultados']);

    $unexpectedErrors = $results->filter(fn (array $result): bool => isset($result['error']) && $result['error'] !== BusinessRuleException::class);
    expect($unexpectedErrors->all())->toBeEmpty();

    return [
        'exitosas' => $results->filter(fn (array $result): bool => array_key_exists('resultado', $result))->map(fn (array $result) => $result['resultado'])->all(),
        'fallidas' => $results->filter(fn (array $result): bool => isset($result['error']))->map(fn (array $result) => $result['mensaje'])->all(),
    ];
}

function crearEstadiaEndeudada(string $debtAmount = '250.00'): Stay
{
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['total_amount' => $debtAmount]);

    return $stay;
}

function abrirSesionEnSucursal(int $branchId): CashSession
{
    return CashSession::factory()->for(CashRegister::factory()->state(['branch_id' => $branchId]))->create();
}

it('registers a payment only once when the same request arrives twice at the same time', function () {
    $stay = crearEstadiaEndeudada();
    $cashSession = abrirSesionEnSucursal($stay->branch_id);
    $userId = User::factory()->create()->id;
    $stayId = $stay->id;
    $cashSessionId = $cashSession->id;
    $operationUuid = (string) Str::uuid();

    $cobrar = static fn () => app(CashService::class)->cobrarPago(
        Stay::findOrFail($stayId), CashSession::findOrFail($cashSessionId), User::findOrFail($userId),
        PaymentMethod::Cash, PaymentType::Settlement, '250.00', $operationUuid,
    )->id;

    ['exitosas' => $succeeded] = separarResultados(ConcurrentRace::ejecutar('stays', $stay->id, [
        'primer envío' => $cobrar,
        'reintento' => $cobrar,
    ]));

    expect($succeeded)->toHaveCount(2)
        ->and(array_unique(array_values($succeeded)))->toHaveCount(1)
        ->and(Payment::count())->toBe(1)
        ->and(CashMovement::count())->toBe(1);
});

it('registers a full refund only once when the same request arrives twice at the same time', function () {
    $payment = Payment::factory()->conTarjeta()->create(['amount' => '250.00']);
    $paymentId = $payment->id;
    $userId = User::factory()->create()->id;
    $operationUuid = (string) Str::uuid();

    $devolver = static fn () => app(CashService::class)->devolverPago(
        Payment::findOrFail($paymentId), User::findOrFail($userId), '250.00', 'Cobro duplicado', $operationUuid,
    )->id;

    ['exitosas' => $succeeded] = separarResultados(ConcurrentRace::ejecutar('payments', $payment->id, [
        'primer envío' => $devolver,
        'reintento' => $devolver,
    ]));

    expect($succeeded)->toHaveCount(2)
        ->and(array_unique(array_values($succeeded)))->toHaveCount(1)
        ->and($payment->refunds()->count())->toBe(1);
});

it('does not let two refunds of different payments exceed the credit together', function () {
    $stay = crearEstadiaEndeudada('250.00');
    $firstPaymentId = Payment::factory()->for($stay)->anticipo()->conTarjeta()->create(['amount' => '250.00'])->id;
    $secondPaymentId = Payment::factory()->for($stay)->anticipo()->conTarjeta()->create(['amount' => '250.00'])->id;
    $userId = User::factory()->create()->id;

    $devolver = function (int $paymentId) use ($userId): Closure {
        return static fn () => app(CashService::class)->devolverPago(
            Payment::findOrFail($paymentId), User::findOrFail($userId), '250.00', 'Anticipo no usado', (string) Str::uuid(),
        )->id;
    };

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('stays', $stay->id, [
        'pago 1' => $devolver($firstPaymentId),
        'pago 2' => $devolver($secondPaymentId),
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toBe(['La devolución supera el saldo a favor del huésped (Bs 0.00). Anule o descuente primero lo cobrado de más.'])
        ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('0.00');
});

it('does not let two payments from different registers exceed the balance together', function () {
    $stay = crearEstadiaEndeudada('250.00');
    $firstSession = abrirSesionEnSucursal($stay->branch_id);
    $secondSession = abrirSesionEnSucursal($stay->branch_id);
    $userId = User::factory()->create()->id;
    $stayId = $stay->id;

    $cobrarEn = function (int $cashSessionId) use ($stayId, $userId): Closure {
        return static fn () => app(CashService::class)->cobrarPago(
            Stay::findOrFail($stayId), CashSession::findOrFail($cashSessionId), User::findOrFail($userId),
            PaymentMethod::Cash, PaymentType::Settlement, '250.00', (string) Str::uuid(),
        )->id;
    };

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('stays', $stay->id, [
        'caja 1' => $cobrarEn($firstSession->id),
        'caja 2' => $cobrarEn($secondSession->id),
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toBe(['El pago supera el saldo pendiente (Bs 0.00).'])
        ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('0.00');
});

it('checks in only one stay when two receptionists take the same room', function () {
    $room = Room::factory()->create(['capacity' => 2]);
    RoomRate::factory()->for($room->branch)->create(['room_type_id' => $room->room_type_id, 'valid_from' => '2020-01-01']);
    $userId = User::factory()->create()->id;
    $roomId = $room->id;

    $registrarIngreso = function (int $guestId) use ($roomId, $userId): Closure {
        return static fn () => app(ReceptionService::class)->registrarIngreso(
            Room::findOrFail($roomId), User::findOrFail($userId), Guest::findOrFail($guestId),
        )->id;
    };

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('rooms', $room->id, [
        'recepción 1' => $registrarIngreso(Guest::factory()->create()->id),
        'recepción 2' => $registrarIngreso(Guest::factory()->create()->id),
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toHaveCount(1)
        ->and(array_values($failed)[0])->toContain('no está disponible')
        ->and(Stay::count())->toBe(1)
        ->and(StayCharge::count())->toBe(1);
});

it('opens only one session when two users open the same register', function () {
    $cashRegister = CashRegister::factory()->create();
    $cashRegisterId = $cashRegister->id;
    $abrir = function (int $userId) use ($cashRegisterId): Closure {
        return static fn () => app(CashService::class)->abrirSesion(
            CashRegister::findOrFail($cashRegisterId), User::findOrFail($userId), '200.00',
        )->id;
    };

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('cash_registers', $cashRegister->id, [
        'turno mañana' => $abrir(User::factory()->create()->id),
        'turno tarde' => $abrir(User::factory()->create()->id),
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toHaveCount(1)
        ->and(CashSession::count())->toBe(1);
});

it('never completes a stay with a charge added during its check-out', function () {
    $stay = crearEstadiaEndeudada('250.00');
    Payment::factory()->for($stay)->create(['amount' => '250.00']);
    $userId = User::factory()->create()->id;
    $stayId = $stay->id;

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('stays', $stay->id, [
        'salida' => static fn () => app(ReceptionService::class)->registrarSalida(Stay::findOrFail($stayId), User::findOrFail($userId))->id,
        'cargo' => static fn () => app(ChargeService::class)->registrarCargo(
            Stay::findOrFail($stayId), User::findOrFail($userId), ChargeType::Service, 'Lavandería', '1', '15.00',
        )->id,
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    $stay->refresh();

    if (array_key_exists('salida', $succeeded)) {
        expect($stay->status)->toBe(StayStatus::Completed)
            ->and($stay->charges()->count())->toBe(1)
            ->and($failed['cargo'])->toContain('no está activa');
    } else {
        expect($stay->status)->toBe(StayStatus::Active)
            ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('15.00')
            ->and($failed['salida'])->toContain('saldo pendiente');
    }
});

it('keeps the expected cash of a closing session consistent with a payment collected meanwhile', function () {
    $stay = crearEstadiaEndeudada('250.00');
    $cashSession = abrirSesionEnSucursal($stay->branch_id);
    $userId = User::factory()->create()->id;
    $stayId = $stay->id;
    $cashSessionId = $cashSession->id;

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('cash_sessions', $cashSession->id, [
        'cierre' => static fn () => app(CashService::class)->cerrarSesion(CashSession::findOrFail($cashSessionId), User::findOrFail($userId), '200.00')->id,
        'cobro' => static fn () => app(CashService::class)->cobrarPago(
            Stay::findOrFail($stayId), CashSession::findOrFail($cashSessionId), User::findOrFail($userId),
            PaymentMethod::Cash, PaymentType::Settlement, '250.00', (string) Str::uuid(),
        )->id,
    ]));

    $cashSession->refresh();
    $collectedInSession = $cashSession->payments()->sum('amount');

    expect($succeeded)->toHaveKey('cierre')
        ->and($cashSession->expected_amount)->toBe(number_format(200 + $collectedInSession, 2, '.', ''));

    if (array_key_exists('cobro', $failed)) {
        expect($failed['cobro'])->toContain('no está abierta');
    }
});

it('does not let two cash expenses take out more than the register holds', function () {
    $cashSession = abrirSesionEnSucursal(Branch::factory()->create()->id);
    $cashSessionId = $cashSession->id;
    $expenseCategoryId = ExpenseCategory::factory()->create()->id;
    $userId = User::factory()->create()->id;

    $gastar = static fn () => app(ExpenseService::class)->registrar(
        CashSession::findOrFail($cashSessionId)->cashRegister->branch, User::findOrFail($userId), ExpenseCategory::findOrFail($expenseCategoryId),
        'Garrafa de gas', '150.00', PaymentMethod::Cash, (string) Str::uuid(), CashSession::findOrFail($cashSessionId),
    )->id;

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('cash_sessions', $cashSession->id, [
        'gasto 1' => $gastar,
        'gasto 2' => $gastar,
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toBe(['El gasto supera el efectivo de la caja (Bs 50.00).'])
        ->and((string) app(CashService::class)->calcularEfectivoEsperado($cashSession))->toBe('50.00');
});

it('gives receipts issued at the same time consecutive numbers', function () {
    $stay = crearEstadiaEndeudada('250.00');
    $sequence = ReceiptSequence::factory()->create(['branch_id' => $stay->branch_id, 'prefix' => 'CEN']);
    $firstPaymentId = Payment::factory()->for($stay)->create()->id;
    $secondPaymentId = Payment::factory()->for($stay)->create()->id;
    $userId = User::factory()->create()->id;

    $emitir = function (int $paymentId) use ($userId): Closure {
        return static fn () => app(ReceiptService::class)->emitir(Payment::findOrFail($paymentId), User::findOrFail($userId), 'Ana Quispe')->number;
    };

    ['exitosas' => $succeeded] = separarResultados(ConcurrentRace::ejecutar('receipt_sequences', $sequence->id, [
        'pago 1' => $emitir($firstPaymentId),
        'pago 2' => $emitir($secondPaymentId),
    ]));

    expect(collect($succeeded)->sort()->values()->all())->toBe([1, 2])
        ->and($sequence->refresh()->next_number)->toBe(3);
});

it('charges each night once when the night audit runs twice at the same time', function () {
    $branch = Branch::factory()->create([
        'timezone' => 'America/La_Paz',
        'current_business_date' => CarbonImmutable::now('America/La_Paz')->subDay()->toDateString(),
    ]);
    $stay = Stay::factory()->for(Room::factory()->for($branch))->create();
    $userId = User::factory()->create()->id;
    $branchId = $branch->id;
    $cerrar = static fn () => app(NightAuditService::class)->cerrarFechaDeNegocio(Branch::findOrFail($branchId), User::findOrFail($userId));

    $race = conHoraDeCierreALaMedianoche(fn () => ConcurrentRace::ejecutar('branches', $branch->id, [
        'servidor 1' => $cerrar,
        'servidor 2' => $cerrar,
    ]));
    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados($race);

    expect(array_values($succeeded))->toBe([1])
        ->and(array_values($failed))->toHaveCount(1)
        ->and($stay->charges()->count())->toBe(1)
        ->and($branch->fresh()->current_business_date->toDateString())->toBe(CarbonImmutable::now('America/La_Paz')->toDateString());
});

/**
 * The child processes read the cut-off hour from the environment. At hour 0
 * yesterday's business date is always due, whatever the time of the run.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $callback
 * @return TResult
 */
function conHoraDeCierreALaMedianoche(Closure $callback): mixed
{
    putenv('LODGING_NIGHT_AUDIT_HOUR=0');
    $_ENV['LODGING_NIGHT_AUDIT_HOUR'] = $_SERVER['LODGING_NIGHT_AUDIT_HOUR'] = '0';

    try {
        return $callback();
    } finally {
        putenv('LODGING_NIGHT_AUDIT_HOUR');
        unset($_ENV['LODGING_NIGHT_AUDIT_HOUR'], $_SERVER['LODGING_NIGHT_AUDIT_HOUR']);
    }
}

it('does not let two exits take out more stock than the warehouse holds', function () {
    $balance = InventoryBalance::factory()->create(['quantity' => '8.000', 'average_cost' => '4.0000']);
    $warehouseId = $balance->warehouse_id;
    $productId = $balance->product_id;
    $userId = User::factory()->create()->id;

    $consumir = static fn () => app(InventoryService::class)->registrarSalida(
        Warehouse::findOrFail($warehouseId), Product::findOrFail($productId), User::findOrFail($userId),
        InventoryMovementType::Consumption, '5', (string) Str::uuid(),
    )->id;

    ['exitosas' => $succeeded, 'fallidas' => $failed] = separarResultados(ConcurrentRace::ejecutar('inventory_balances', $balance->id, [
        'consumo 1' => $consumir,
        'consumo 2' => $consumir,
    ]));

    expect($succeeded)->toHaveCount(1)
        ->and(array_values($failed))->toBe(['No hay existencias suficientes: quedan 3.000 (unidad).'])
        ->and($balance->refresh()->quantity)->toBe('3.000');
});
