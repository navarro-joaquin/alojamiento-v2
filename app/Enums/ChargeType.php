<?php

namespace App\Enums;

enum ChargeType: string
{
    case Lodging = 'lodging';
    case LateCheckOut = 'late_check_out';
    case Product = 'product';
    case Service = 'service';
    case Discount = 'discount';
    case Adjustment = 'adjustment';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lodging => 'Alojamiento',
            self::LateCheckOut => 'Salida tardía',
            self::Product => 'Producto',
            self::Service => 'Servicio',
            self::Discount => 'Descuento',
            self::Adjustment => 'Ajuste',
        };
    }
}
