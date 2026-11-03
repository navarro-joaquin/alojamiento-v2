<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case InService = 'in_service';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function etiqueta(): string
    {
        return match ($this) {
            self::InService => 'En servicio',
            self::Maintenance => 'En mantenimiento',
            self::OutOfService => 'Fuera de servicio',
        };
    }
}
