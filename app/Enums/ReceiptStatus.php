<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Issued = 'issued';
    case Voided = 'voided';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Issued => 'Emitido',
            self::Voided => 'Anulado',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::Issued => 'green',
            self::Voided => 'red',
        };
    }
}
