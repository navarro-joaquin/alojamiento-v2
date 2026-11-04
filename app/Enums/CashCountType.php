<?php

namespace App\Enums;

enum CashCountType: string
{
    case Partial = 'partial';
    case Closing = 'closing';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Partial => 'Arqueo parcial',
            self::Closing => 'Cierre',
        };
    }
}
