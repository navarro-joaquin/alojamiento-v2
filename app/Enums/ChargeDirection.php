<?php

namespace App\Enums;

enum ChargeDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Debit => 'Débito',
            self::Credit => 'Crédito',
        };
    }
}
