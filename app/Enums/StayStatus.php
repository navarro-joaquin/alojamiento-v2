<?php

namespace App\Enums;

enum StayStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Completed => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }
}
