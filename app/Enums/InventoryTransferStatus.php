<?php

namespace App\Enums;

enum InventoryTransferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'En tránsito',
            self::Received => 'Recibida',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'amber',
            self::Received => 'green',
            self::Cancelled => 'red',
        };
    }
}
