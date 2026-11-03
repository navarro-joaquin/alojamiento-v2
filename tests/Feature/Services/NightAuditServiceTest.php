<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\NightAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearSucursalEnFecha(string $businessDate): Branch
{
    return Branch::factory()->create(['current_business_date' => $businessDate, 'timezone' => 'America/La_Paz']);
}

function viajarAHoraDeLaPaz(string $localDateTime): void
{
    test()->travelTo(CarbonImmutable::parse($localDateTime, 'America/La_Paz'));
}

it('allows closing the business date from the cut-off hour of the next day', function (string $localDateTime, bool $canClose) {
    $branch = crearSucursalEnFecha('2026-11-03');
    viajarAHoraDeLaPaz($localDateTime);

    expect(app(NightAuditService::class)->puedeCerrar($branch))->toBe($canClose);
})->with([
    'same evening' => ['2026-11-03 23:59', false],
    'just before the cut-off' => ['2026-11-04 03:59', false],
    'at the cut-off' => ['2026-11-04 04:00', true],
    'several days later' => ['2026-11-07 10:00', true],
]);

it('charges the night to every active stay of the branch and advances the date', function () {
    $branch = crearSucursalEnFecha('2026-11-03');
    $activeStays = collect([
        Stay::factory()->for(Room::factory()->for($branch))->create(),
        Stay::factory()->for(Room::factory()->for($branch))->create(),
    ]);
    viajarAHoraDeLaPaz('2026-11-04 04:00');

    $chargedNights = app(NightAuditService::class)->cerrarFechaDeNegocio($branch, User::factory()->create());

    expect($chargedNights)->toBe(2)
        ->and($branch->fresh()->current_business_date->toDateString())->toBe('2026-11-04');
    $activeStays->each(fn (Stay $stay) => expect($stay->charges()->sole()->service_date->toDateString())->toBe('2026-11-03'));
    $this->assertDatabaseHas('activity_log', ['event' => 'business_date.closed', 'subject_id' => $branch->id]);
});

it('does not charge again a night already charged at check-in', function () {
    $branch = crearSucursalEnFecha('2026-11-03');
    $stay = Stay::factory()->for(Room::factory()->for($branch))->create();
    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03']);
    viajarAHoraDeLaPaz('2026-11-04 04:00');

    $chargedNights = app(NightAuditService::class)->cerrarFechaDeNegocio($branch, User::factory()->create());

    expect($chargedNights)->toBe(0)
        ->and($stay->charges()->count())->toBe(1);
});

it('ignores completed stays and stays of other branches', function () {
    $branch = crearSucursalEnFecha('2026-11-03');
    $completedStay = Stay::factory()->for(Room::factory()->for($branch))->finalizada()->create();
    $otherBranchStay = Stay::factory()->for(Room::factory()->for(crearSucursalEnFecha('2026-11-03')))->create();
    viajarAHoraDeLaPaz('2026-11-04 04:00');

    app(NightAuditService::class)->cerrarFechaDeNegocio($branch, User::factory()->create());

    expect($completedStay->charges()->exists())->toBeFalse()
        ->and($otherBranchStay->charges()->exists())->toBeFalse();
});

it('rejects closing a business date before it is due', function () {
    $branch = crearSucursalEnFecha('2026-11-03');
    viajarAHoraDeLaPaz('2026-11-04 03:00');

    app(NightAuditService::class)->cerrarFechaDeNegocio($branch, User::factory()->create());
})->throws(BusinessRuleException::class, 'ya está al día');
