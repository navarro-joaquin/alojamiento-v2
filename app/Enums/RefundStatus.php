<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Confirmed = 'confirmed';
    case Voided = 'voided';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Voided => 'Anulada',
        };
    }
}
