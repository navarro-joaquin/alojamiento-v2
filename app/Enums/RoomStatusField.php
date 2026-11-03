<?php

namespace App\Enums;

enum RoomStatusField: string
{
    case Housekeeping = 'housekeeping_status';
    case Operational = 'operational_status';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Housekeeping => 'Limpieza',
            self::Operational => 'Operativo',
        };
    }
}
