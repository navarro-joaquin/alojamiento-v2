<?php

use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Services\StayBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns zero for a stay without charges or payments', function () {
    $stay = Stay::factory()->create();

    expect((string) app(StayBalanceService::class)->calcular($stay))->toBe('0.00');
});

it('subtracts credits and payments from debits and adds refunds back', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03', 'total_amount' => '250.00']);
    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-04', 'total_amount' => '250.00']);
    StayCharge::factory()->for($stay)->descuento()->create(['total_amount' => '30.50']);
    $payment = Payment::factory()->for($stay)->create(['amount' => '300.00']);
    PaymentRefund::factory()->for($payment)->create(['amount' => '20.25']);

    expect((string) app(StayBalanceService::class)->calcular($stay))->toBe('189.75');
});

it('ignores voided charges, payments and refunds', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03', 'total_amount' => '250.00']);
    StayCharge::factory()->for($stay)->anulado()->create(['service_date' => '2026-11-04', 'total_amount' => '250.00']);
    $payment = Payment::factory()->for($stay)->create(['amount' => '100.00']);
    Payment::factory()->for($stay)->create(['amount' => '100.00', 'status' => 'voided']);
    PaymentRefund::factory()->for($payment)->create(['amount' => '40.00', 'status' => 'voided']);

    expect((string) app(StayBalanceService::class)->calcular($stay))->toBe('150.00');
});

it('returns a negative balance when the guest has paid more than was charged', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['total_amount' => '250.00']);
    Payment::factory()->for($stay)->anticipo()->create(['amount' => '500.00']);

    expect((string) app(StayBalanceService::class)->calcular($stay))->toBe('-250.00');
});

it('does not count charges, payments or refunds of other stays', function () {
    $stay = Stay::factory()->create();
    StayCharge::factory()->for($stay)->create(['total_amount' => '250.00']);
    $otherPayment = Payment::factory()->create(['amount' => '250.00']);
    StayCharge::factory()->for($otherPayment->stay)->create(['total_amount' => '250.00']);
    PaymentRefund::factory()->for($otherPayment)->create(['amount' => '100.00']);

    expect((string) app(StayBalanceService::class)->calcular($stay))->toBe('250.00');
});
