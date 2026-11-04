<?php

use App\Enums\CashDenomination;

it('keeps only known denominations with a positive quantity', function () {
    expect(CashDenomination::filtrarConteo(['20000' => '2', '5000' => '', '50' => '3', '1000' => '0', '999' => '4']))
        ->toBe([20000 => 2, 50 => 3]);
});

it('adds up bills and coins in bolivianos', function () {
    expect((string) CashDenomination::sumar([20000 => 2, 1000 => 1, 50 => 3, 10 => 1]))->toBe('411.60');
});

it('labels bills and coins', function () {
    expect(CashDenomination::Bill200->etiqueta())->toBe('Billete de Bs 200')
        ->and(CashDenomination::Coin2->etiqueta())->toBe('Moneda de Bs 2')
        ->and(CashDenomination::Coin050->etiqueta())->toBe('Moneda de 50 ctvs.');
});
