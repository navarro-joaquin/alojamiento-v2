<?php

namespace App\Enums;

enum CheckoutExceptionStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Used = 'used';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Requested => 'Pendiente de aprobación',
            self::Approved => 'Autorizada',
            self::Rejected => 'Rechazada',
            self::Used => 'Usada',
            self::Revoked => 'Revocada',
            self::Expired => 'Vencida',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved => 'green',
            self::Rejected, self::Revoked => 'red',
            self::Used => 'blue',
            self::Expired => 'gray',
        };
    }
}
