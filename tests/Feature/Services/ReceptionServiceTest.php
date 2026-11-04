<?php

use App\Enums\ChargeType;
use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Enums\PaymentMethod;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\FolioBalanceService;
use App\Services\ReceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearSucursalDeRecepcion(): Branch
{
    return Branch::factory()->create(['current_business_date' => '2026-11-03']);
}

/**
 * A clean room in service with a valid rate for its type.
 */
function crearHabitacionConTarifa(Branch $branch, string $rateAmount = '250.00'): Room
{
    $room = Room::factory()->for($branch)->create(['capacity' => 2]);
    RoomRate::factory()->for($branch)->create([
        'room_type_id' => $room->room_type_id,
        'amount' => $rateAmount,
        'valid_from' => '2026-01-01',
    ]);

    return $room;
}

function registrarIngresoEn(Room $room, ?Guest $primaryGuest = null): Stay
{
    return app(ReceptionService::class)->registrarIngreso($room, User::factory()->create(), $primaryGuest ?? Guest::factory()->create());
}

describe('registrarIngreso', function () {
    it('creates the stay at the resolved rate with its guests, folio and first night', function () {
        $room = crearHabitacionConTarifa(crearSucursalDeRecepcion(), '280.00');
        $primaryGuest = Guest::factory()->create();
        $companion = Guest::factory()->create();

        $stay = app(ReceptionService::class)->registrarIngreso($room, User::factory()->create(), $primaryGuest, [$companion], adults: 2);

        expect($stay->status)->toBe(StayStatus::Active)
            ->and($stay->rate_amount)->toBe('280.00')
            ->and($stay->guests()->wherePivot('is_primary', true)->sole()->is($primaryGuest))->toBeTrue()
            ->and($stay->guests()->count())->toBe(2)
            ->and($stay->roomAssignments()->sole()->room_id)->toBe($room->id);

        $firstNight = $stay->charges()->sole();
        expect($firstNight->folio_id)->toBe($stay->folio->id)
            ->and($stay->folio->branch_id)->toBe($room->branch_id)
            ->and($firstNight->charge_type)->toBe(ChargeType::Lodging)
            ->and($firstNight->service_date->toDateString())->toBe('2026-11-03')
            ->and($firstNight->total_amount)->toBe('280.00');

        expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty);
        $this->assertDatabaseHas('activity_log', ['event' => 'stay.checked_in', 'subject_id' => $stay->id]);
    });

    it('rejects a room that is not ready', function (array $roomState) {
        $room = crearHabitacionConTarifa(crearSucursalDeRecepcion());
        $room->update($roomState);

        registrarIngresoEn($room);
    })->with([
        'dirty' => [['housekeeping_status' => HousekeepingStatus::Dirty]],
        'under maintenance' => [['operational_status' => OperationalStatus::Maintenance]],
    ])->throws(BusinessRuleException::class, 'no está disponible');

    it('rejects a room that is already occupied', function () {
        $room = crearHabitacionConTarifa(crearSucursalDeRecepcion());
        Stay::factory()->for($room)->create();

        registrarIngresoEn($room);
    })->throws(BusinessRuleException::class, 'ya está ocupada');

    it('rejects more guests than the room capacity', function () {
        $room = crearHabitacionConTarifa(crearSucursalDeRecepcion());

        app(ReceptionService::class)->registrarIngreso($room, User::factory()->create(), Guest::factory()->create(), adults: 2, children: 1);
    })->throws(BusinessRuleException::class, 'como máximo 2 huéspedes');

    it('rejects the primary guest listed again as a companion', function () {
        $room = crearHabitacionConTarifa(crearSucursalDeRecepcion());
        $guest = Guest::factory()->create();

        app(ReceptionService::class)->registrarIngreso($room, User::factory()->create(), $guest, [$guest], adults: 2);
    })->throws(BusinessRuleException::class, 'dos veces');

    it('rejects a room type without a valid rate and leaves the room untouched', function () {
        $room = Room::factory()->for(crearSucursalDeRecepcion())->create();

        expect(fn () => registrarIngresoEn($room))->toThrow(BusinessRuleException::class, 'No hay una tarifa vigente');

        expect(Stay::count())->toBe(0)
            ->and($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Clean);
    });
});

describe('cambiarHabitacion', function () {
    it('moves the stay to the new room at its rate and closes the previous assignment', function () {
        $branch = crearSucursalDeRecepcion();
        $stay = registrarIngresoEn(crearHabitacionConTarifa($branch, '250.00'));
        $previousRoom = $stay->room;
        $newRoom = crearHabitacionConTarifa($branch, '320.00');

        $movedStay = app(ReceptionService::class)->cambiarHabitacion($stay, $newRoom, User::factory()->create(), 'Aire acondicionado averiado');

        expect($movedStay->room_id)->toBe($newRoom->id)
            ->and($movedStay->rate_amount)->toBe('320.00')
            ->and($stay->roomAssignments()->whereNull('assigned_to')->sole()->room_id)->toBe($newRoom->id)
            ->and($stay->roomAssignments()->count())->toBe(2)
            ->and($previousRoom->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty)
            ->and($newRoom->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty);
    });

    it('keeps an agreed rate instead of the new room rate', function () {
        $branch = crearSucursalDeRecepcion();
        $stay = registrarIngresoEn(crearHabitacionConTarifa($branch, '250.00'));

        $movedStay = app(ReceptionService::class)->cambiarHabitacion(
            $stay, crearHabitacionConTarifa($branch, '320.00'), User::factory()->create(), 'Cortesía', agreedRateAmount: '250.00',
        );

        expect($movedStay->rate_amount)->toBe('250.00')
            ->and($movedStay->room_rate_id)->toBeNull();
    });

    it('rejects a room of another branch', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));

        app(ReceptionService::class)->cambiarHabitacion($stay, crearHabitacionConTarifa(crearSucursalDeRecepcion()), User::factory()->create(), 'Traslado');
    })->throws(BusinessRuleException::class, 'pertenece a otra sucursal');

    it('rejects a room that is already occupied', function () {
        $branch = crearSucursalDeRecepcion();
        $stay = registrarIngresoEn(crearHabitacionConTarifa($branch));
        $occupiedRoom = crearHabitacionConTarifa($branch);
        Stay::factory()->for($occupiedRoom)->create();

        app(ReceptionService::class)->cambiarHabitacion($stay, $occupiedRoom, User::factory()->create(), 'Traslado');
    })->throws(BusinessRuleException::class, 'ya está ocupada');

    it('rejects moving the stay to its current room', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));

        app(ReceptionService::class)->cambiarHabitacion($stay, $stay->room, User::factory()->create(), 'Traslado');
    })->throws(BusinessRuleException::class, 'ya está en esa habitación');
});

describe('registrarSalida', function () {
    it('completes a settled stay and frees the room', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));
        Payment::factory()->for($stay->folio)->create(['amount' => '250.00']);

        $completedStay = app(ReceptionService::class)->registrarSalida($stay, User::factory()->create());

        expect($completedStay->status)->toBe(StayStatus::Completed)
            ->and($completedStay->check_out_at)->not->toBeNull()
            ->and($stay->roomAssignments()->whereNull('assigned_to')->exists())->toBeFalse()
            ->and($stay->room->activeStay)->toBeNull();
        $this->assertDatabaseHas('activity_log', ['event' => 'stay.checked_out', 'subject_id' => $stay->id]);
    });

    it('rejects a stay with outstanding debt', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));

        app(ReceptionService::class)->registrarSalida($stay, User::factory()->create());
    })->throws(BusinessRuleException::class, 'saldo pendiente de Bs 250.00');

    it('refunds the credit in cash from the open session before leaving', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));
        $deposit = Payment::factory()->for($stay->folio)->anticipo()->create(['amount' => '500.00']);

        app(ReceptionService::class)->registrarSalida($stay, User::factory()->create(), $deposit->cashSession);

        $refund = $deposit->refunds()->sole();
        expect($refund->amount)->toBe('250.00')
            ->and($refund->refund_method)->toBe(PaymentMethod::Cash)
            ->and($refund->cashMovements()->exists())->toBeTrue()
            ->and((string) app(FolioBalanceService::class)->calcular($stay->folio))->toBe('0.00');
    });

    it('refunds the credit starting with the most recent payment', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));
        $olderCashPayment = Payment::factory()->for($stay->folio)->anticipo()->create(['amount' => '200.00', 'paid_at' => now()->subHour()]);
        $newerCardPayment = Payment::factory()->for($stay->folio)->anticipo()->conTarjeta()->create(['amount' => '200.00', 'paid_at' => now()]);

        app(ReceptionService::class)->registrarSalida($stay, User::factory()->create());

        expect($newerCardPayment->refunds()->sole()->amount)->toBe('150.00')
            ->and($olderCashPayment->refunds()->exists())->toBeFalse();
    });

    it('rejects a cash refund without a session and keeps the stay active', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));
        Payment::factory()->for($stay->folio)->anticipo()->create(['amount' => '500.00']);

        expect(fn () => app(ReceptionService::class)->registrarSalida($stay, User::factory()->create()))
            ->toThrow(BusinessRuleException::class, 'requiere una sesión de caja abierta');

        expect($stay->fresh()->status)->toBe(StayStatus::Active);
    });

    it('rejects a credit that no payment can cover', function () {
        $stay = registrarIngresoEn(crearHabitacionConTarifa(crearSucursalDeRecepcion()));
        StayCharge::factory()->for($stay)->descuento()->create(['total_amount' => '300.00']);

        app(ReceptionService::class)->registrarSalida($stay, User::factory()->create());
    })->throws(BusinessRuleException::class, 'faltan Bs 50.00');

    it('rejects a stay that is no longer active', function () {
        $stay = Stay::factory()->finalizada()->create();

        app(ReceptionService::class)->registrarSalida($stay, User::factory()->create());
    })->throws(BusinessRuleException::class, 'no está activa');
});
