<?php

use App\Enums\PaymentMethod;

test('only cash moves physical cash', function (PaymentMethod $paymentMethod, bool $affectsCash) {
    expect($paymentMethod->afectaEfectivo())->toBe($affectsCash);
})->with([
    'cash' => [PaymentMethod::Cash, true],
    'card' => [PaymentMethod::Card, false],
    'bank transfer' => [PaymentMethod::BankTransfer, false],
    'qr' => [PaymentMethod::Qr, false],
]);
