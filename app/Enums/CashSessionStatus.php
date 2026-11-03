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
     * Whether new payments, refunds or expenses may be recorded in the session.
     */
    public function aceptaMovimientos(): bool
    {
        return $this === self::Open;
    }
}
