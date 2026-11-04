<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pendiente de aprobación',
            self::Confirmed => 'Confirmado',
            self::Rejected => 'Rechazado',
            self::Voided => 'Anulado',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::PendingApproval => 'amber',
            self::Confirmed => 'green',
            self::Rejected, self::Voided => 'red',
        };
    }
}
