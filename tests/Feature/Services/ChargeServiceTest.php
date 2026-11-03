<?php

use App\Enums\ChargeDirection;
use App\Enums\ChargeStatus;
use App\Enums\ChargeType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\ChargeService;
use App\Services\StayBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearEstadiaParaCargos(): Stay
{
    $branch = Branch::factory()->create(['current_business_date' => '2026-11-03']);

    return Stay::factory()->for(Room::factory()->for($branch))->create(['rate_amount' => '250.00']);
}

describe('registrarNocheAlojamiento', function () {
    it('charges the night at the agreed rate of the stay', function () {
        $stay = crearEstadiaParaCargos();

        $stayCharge = app(ChargeService::class)->registrarNocheAlojamiento($stay, User::factory()->create(), CarbonImmutable::parse('2026-11-03'));

        expect($stayCharge->charge_type)->toBe(ChargeType::Lodging)
            ->and($stayCharge->service_date->toDateString())->toBe('2026-11-03')
            ->and($stayCharge->total_amount)->toBe('250.00')
            ->and($stayCharge->wasRecentlyCreated)->toBeTrue();
    });

    it('returns the existing charge when the night is already charged', function () {
        $stay = crearEstadiaParaCargos();
        $user = User::factory()->create();
        $firstCharge = app(ChargeService::class)->registrarNocheAlojamiento($stay, $user, CarbonImmutable::parse('2026-11-03'));

        $secondCharge = app(ChargeService::class)->registrarNocheAlojamiento($stay->fresh(), $user, CarbonImmutable::parse('2026-11-03'));

        expect($secondCharge->is($firstCharge))->toBeTrue()
            ->and($secondCharge->wasRecentlyCreated)->toBeFalse()
            ->and($stay->charges()->count())->toBe(1);
    });
});

describe('registrarCargo', function () {
    it('charges quantity times unit price on the branch business date', function () {
        $stay = crearEstadiaParaCargos();

        $stayCharge = app(ChargeService::class)->registrarCargo($stay, User::factory()->create(), ChargeType::Service, 'Lavandería', '3', '12.50');

        expect($stayCharge->total_amount)->toBe('37.50')
            ->and($stayCharge->direction)->toBe(ChargeDirection::Debit)
            ->and($stayCharge->service_date->toDateString())->toBe('2026-11-03');
        $this->assertDatabaseHas('activity_log', ['event' => 'stay_charge.added', 'subject_id' => $stayCharge->id]);
    });

    it('registers a manual late check-out charge', function () {
        $stay = crearEstadiaParaCargos();

        $stayCharge = app(ChargeService::class)->registrarCargo($stay, User::factory()->create(), ChargeType::LateCheckOut, 'Salida a las 16:00', '1', '80.00');

        expect($stayCharge->charge_type)->toBe(ChargeType::LateCheckOut)
            ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('80.00');
    });

    it('rejects types that are not charged manually', function (ChargeType $chargeType) {
        app(ChargeService::class)->registrarCargo(crearEstadiaParaCargos(), User::factory()->create(), $chargeType, 'Cargo', '1', '10.00');
    })->with([
        'lodging' => ChargeType::Lodging,
        'discount' => ChargeType::Discount,
    ])->throws(BusinessRuleException::class, 'no se puede registrar manualmente');

    it('rejects a quantity that is not positive', function () {
        app(ChargeService::class)->registrarCargo(crearEstadiaParaCargos(), User::factory()->create(), ChargeType::Product, 'Agua', '0', '10.00');
    })->throws(BusinessRuleException::class, 'mayor que cero');

    it('rejects a negative unit price', function () {
        app(ChargeService::class)->registrarCargo(crearEstadiaParaCargos(), User::factory()->create(), ChargeType::Product, 'Agua', '1', '-10.00');
    })->throws(BusinessRuleException::class, 'no puede ser negativo');

    it('rejects a stay that is no longer active', function () {
        $stay = Stay::factory()->finalizada()->create();

        app(ChargeService::class)->registrarCargo($stay, User::factory()->create(), ChargeType::Product, 'Agua', '1', '10.00');
    })->throws(BusinessRuleException::class, 'no está activa');
});

describe('registrarDescuento', function () {
    it('credits the amount against the balance', function () {
        $stay = crearEstadiaParaCargos();
        StayCharge::factory()->for($stay)->create(['total_amount' => '250.00']);

        $discount = app(ChargeService::class)->registrarDescuento($stay, User::factory()->create(), 'Cliente frecuente', '25.00');

        expect($discount->direction)->toBe(ChargeDirection::Credit)
            ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('225.00');
    });

    it('rejects an amount that is not positive', function () {
        app(ChargeService::class)->registrarDescuento(crearEstadiaParaCargos(), User::factory()->create(), 'Descuento', '0.00');
    })->throws(BusinessRuleException::class, 'mayor que cero');
});

describe('anularCargo', function () {
    it('voids the charge with its reason and removes it from the balance', function () {
        $stay = crearEstadiaParaCargos();
        $stayCharge = StayCharge::factory()->for($stay)->create(['total_amount' => '250.00']);
        $user = User::factory()->create();

        $voidedCharge = app(ChargeService::class)->anularCargo($stayCharge, $user, 'Cargo duplicado');

        expect($voidedCharge->status)->toBe(ChargeStatus::Voided)
            ->and($voidedCharge->voided_by)->toBe($user->id)
            ->and($voidedCharge->void_reason)->toBe('Cargo duplicado')
            ->and((string) app(StayBalanceService::class)->calcular($stay))->toBe('0.00');
    });

    it('rejects a charge that is already voided', function () {
        $stayCharge = StayCharge::factory()->for(crearEstadiaParaCargos())->anulado()->create();

        app(ChargeService::class)->anularCargo($stayCharge, User::factory()->create(), 'Otra vez');
    })->throws(BusinessRuleException::class, 'ya está anulado');
});
