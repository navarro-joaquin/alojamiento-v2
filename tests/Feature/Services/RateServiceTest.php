<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Room;
use App\Models\RoomRate;
use App\Services\RateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Wednesday, 4 November 2026 (ISO weekday 3).
 */
function fechaDeTarifa(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-04');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function crearTarifaPara(Room $room, array $attributes = []): RoomRate
{
    return RoomRate::factory()->create([
        'branch_id' => $room->branch_id,
        'room_type_id' => $room->room_type_id,
        'valid_from' => '2026-11-01',
        ...$attributes,
    ]);
}

describe('resolverTarifa', function () {
    it('resolves the active rate of the room branch and type valid on the date', function () {
        $room = Room::factory()->create();
        $rate = crearTarifaPara($room);

        expect(app(RateService::class)->resolverTarifa($room, 2, fechaDeTarifa())->is($rate))->toBeTrue();
    });

    it('prefers the rate with the highest priority', function () {
        $room = Room::factory()->create();
        crearTarifaPara($room, ['amount' => '250.00']);
        $promotionalRate = crearTarifaPara($room, ['amount' => '200.00', 'priority' => 10]);

        expect(app(RateService::class)->resolverTarifa($room, 2, fechaDeTarifa())->is($promotionalRate))->toBeTrue();
    });

    it('prefers a rate for the exact occupancy over a generic one at the same priority', function () {
        $room = Room::factory()->create();
        crearTarifaPara($room);
        $singleRate = crearTarifaPara($room, ['amount' => '180.00', 'occupancy' => 1]);

        expect(app(RateService::class)->resolverTarifa($room, 1, fechaDeTarifa())->is($singleRate))->toBeTrue();
    });

    it('applies a weekday rate only on its days', function (array $daysOfWeek, bool $applies) {
        $room = Room::factory()->create();
        $generalRate = crearTarifaPara($room);
        $weekdayRate = crearTarifaPara($room, ['amount' => '300.00', 'days_of_week' => $daysOfWeek]);

        $resolvedRate = app(RateService::class)->resolverTarifa($room, 2, fechaDeTarifa());

        expect($resolvedRate->is($applies ? $weekdayRate : $generalRate))->toBeTrue();
    })->with([
        'includes wednesday' => [[3, 4], true],
        'weekend only' => [[6, 7], false],
    ]);

    it('ignores rates that are inactive, expired, not yet valid or from another branch', function () {
        $room = Room::factory()->create();
        $currentRate = crearTarifaPara($room);
        crearTarifaPara($room, ['priority' => 10, 'is_active' => false]);
        crearTarifaPara($room, ['priority' => 10, 'valid_from' => '2026-10-01', 'valid_to' => '2026-11-03']);
        crearTarifaPara($room, ['priority' => 10, 'valid_from' => '2026-11-05']);
        RoomRate::factory()->create(['room_type_id' => $room->room_type_id, 'priority' => 10, 'valid_from' => '2026-11-01']);

        expect(app(RateService::class)->resolverTarifa($room, 2, fechaDeTarifa())->is($currentRate))->toBeTrue();
    });

    it('rejects a room type without a valid rate', function () {
        $room = Room::factory()->create();

        app(RateService::class)->resolverTarifa($room, 2, fechaDeTarifa());
    })->throws(BusinessRuleException::class, 'No hay una tarifa vigente');
});

describe('verificarSinSolapamiento', function () {
    it('rejects a rate that competes with another on the same terms', function () {
        $room = Room::factory()->create();
        crearTarifaPara($room, ['valid_to' => '2026-11-30']);
        $newRate = RoomRate::factory()->make([
            'branch_id' => $room->branch_id,
            'room_type_id' => $room->room_type_id,
            'valid_from' => '2026-11-15',
        ]);

        app(RateService::class)->verificarSinSolapamiento($newRate);
    })->throws(BusinessRuleException::class, 'Ya existe una tarifa');

    it('rejects weekday rates that share a day', function () {
        $room = Room::factory()->create();
        crearTarifaPara($room, ['days_of_week' => [5, 6]]);
        $newRate = RoomRate::factory()->make([
            'branch_id' => $room->branch_id,
            'room_type_id' => $room->room_type_id,
            'valid_from' => '2026-11-01',
            'days_of_week' => [6, 7],
        ]);

        app(RateService::class)->verificarSinSolapamiento($newRate);
    })->throws(BusinessRuleException::class, 'Ya existe una tarifa');

    it('accepts a rate that differs from the existing one', function (array $newRateAttributes) {
        $room = Room::factory()->create();
        crearTarifaPara($room, ['valid_to' => '2026-11-30', 'days_of_week' => [1, 2, 3, 4, 5]]);
        $newRate = RoomRate::factory()->make([
            'branch_id' => $room->branch_id,
            'room_type_id' => $room->room_type_id,
            'valid_from' => '2026-11-01',
            'days_of_week' => [1, 2, 3, 4, 5],
            ...$newRateAttributes,
        ]);

        app(RateService::class)->verificarSinSolapamiento($newRate);
    })->throwsNoExceptions()->with([
        'higher priority' => [['priority' => 10]],
        'later dates' => [['valid_from' => '2026-12-01']],
        'specific occupancy' => [['occupancy' => 1]],
        'disjoint weekdays' => [['days_of_week' => [6, 7]]],
        'every day versus weekdays' => [['days_of_week' => null]],
    ]);

    it('does not compare a saved rate with itself', function () {
        $room = Room::factory()->create();
        $rate = crearTarifaPara($room);

        $rate->amount = '275.00';
        app(RateService::class)->verificarSinSolapamiento($rate);
    })->throwsNoExceptions();
});
