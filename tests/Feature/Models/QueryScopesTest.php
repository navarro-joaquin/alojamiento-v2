<?php

use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Stay;
use App\Models\StayCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('lists only active stays as active', function () {
    $activeStay = Stay::factory()->create();
    Stay::factory()->finalizada()->create();

    expect(Stay::activas()->pluck('id')->all())->toBe([$activeStay->id]);
});

test('leaves voided stay charges out of confirmed charges', function () {
    $stay = Stay::factory()->create();
    $confirmedCharge = StayCharge::factory()->for($stay)->create(['service_date' => '2026-11-03']);
    StayCharge::factory()->for($stay)->anulado()->create(['service_date' => '2026-11-04']);

    expect(StayCharge::confirmados()->pluck('id')->all())->toBe([$confirmedCharge->id]);
});

test('leaves voided payments out of confirmed payments', function () {
    $confirmedPayment = Payment::factory()->create();
    Payment::factory()->create(['status' => 'voided']);

    expect(Payment::confirmados()->pluck('id')->all())->toBe([$confirmedPayment->id]);
});

test('leaves voided refunds out of confirmed refunds', function () {
    $confirmedRefund = PaymentRefund::factory()->create();
    PaymentRefund::factory()->create(['status' => 'voided']);

    expect(PaymentRefund::confirmadas()->pluck('id')->all())->toBe([$confirmedRefund->id]);
});

test('leaves closing and closed sessions out of open sessions', function () {
    $openSession = CashSession::factory()->create();
    CashSession::factory()->enCierre()->create();
    CashSession::factory()->cerrada()->create();

    expect(CashSession::abiertas()->pluck('id')->all())->toBe([$openSession->id]);
});
