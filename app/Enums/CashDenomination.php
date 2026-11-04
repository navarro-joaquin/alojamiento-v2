<?php

namespace App\Enums;

use BcMath\Number;

/**
 * Bolivian bills and coins, backed by their value in centavos so they can be
 * used as form keys without decimal points.
 */
enum CashDenomination: int
{
    case Bill200 = 20000;
    case Bill100 = 10000;
    case Bill50 = 5000;
    case Bill20 = 2000;
    case Bill10 = 1000;
    case Coin5 = 500;
    case Coin2 = 200;
    case Coin1 = 100;
    case Coin050 = 50;
    case Coin020 = 20;
    case Coin010 = 10;

    public function etiqueta(): string
    {
        return $this->value >= 1000
            ? 'Billete de Bs '.intdiv($this->value, 100)
            : ($this->value >= 100 ? 'Moneda de Bs '.intdiv($this->value, 100) : "Moneda de {$this->value} ctvs.");
    }

    public function valor(): Number
    {
        return (new Number($this->value) / 100)->round(2);
    }

    /**
     * Keep only known denominations with a positive quantity.
     *
     * @param  array<int|string, int|string|null>  $quantities
     * @return array<int, int> quantity by value in centavos
     */
    public static function filtrarConteo(array $quantities): array
    {
        $counted = [];

        foreach (self::cases() as $denomination) {
            $quantity = (int) ($quantities[$denomination->value] ?? 0);

            if ($quantity > 0) {
                $counted[$denomination->value] = $quantity;
            }
        }

        return $counted;
    }

    /**
     * @param  array<int, int>  $quantities  quantity by value in centavos
     */
    public static function sumar(array $quantities): Number
    {
        $total = new Number('0.00');

        foreach ($quantities as $value => $quantity) {
            $total += self::from((int) $value)->valor() * $quantity;
        }

        return $total->round(2);
    }
}
