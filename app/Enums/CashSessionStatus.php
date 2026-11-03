<?php

namespace App\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case PendingReview = 'pending_review';
    case Closed = 'closed';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closing => 'En cierre',
            self::PendingReview => 'Pendiente de revisión',
            self::Closed => 'Cerrada',
        };
    }

    /**
     * Color of the status badge (see the badge Blade component).
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Closing => 'blue',
            self::PendingReview => 'amber',
            self::Closed => 'gray',
        };
    }

    /**
     * Whether new payments, refunds or expenses may be recorded in the session.
     */
    public function aceptaMovimientos(): bool
    {
        return $this === self::Open;
    }
}
