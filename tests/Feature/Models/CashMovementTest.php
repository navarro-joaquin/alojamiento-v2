<?php

use App\Models\CashMovement;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('stores the morph alias of its source instead of the class name', function () {
    $cashMovement = CashMovement::factory()->create();

    expect(DB::table('cash_movements')->where('id', $cashMovement->id)->value('source_type'))->toBe('payment');
});

it('resolves the payment that produced it', function () {
    $payment = Payment::factory()->create();
    $cashMovement = CashMovement::factory()->for($payment, 'source')->create();

    expect($cashMovement->source->is($payment))->toBeTrue()
        ->and($payment->cashMovements->first()->is($cashMovement))->toBeTrue();
});
