<?php

namespace App\Enums;

enum InventoryMovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function etiqueta(): string
    {
        return match ($this) {
            self::In => 'Entrada',
            self::Out => 'Salida',
        };
    }
}
