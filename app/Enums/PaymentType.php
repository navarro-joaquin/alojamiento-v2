<?php

namespace App\Enums;

enum PaymentType: string
{
    case Deposit = 'deposit';
    case Settlement = 'settlement';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Deposit => 'Anticipo',
            self::Settlement => 'Pago de saldo',
        };
    }
}
