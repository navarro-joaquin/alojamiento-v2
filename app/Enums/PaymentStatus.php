<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Confirmed = 'confirmed';
    case Voided = 'voided';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Voided => 'Anulado',
        };
    }
}
