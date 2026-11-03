<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Qr = 'qr';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::BankTransfer => 'Transferencia bancaria',
            self::Qr => 'QR',
        };
    }

    /**
     * Whether the method moves physical cash in the register.
     */
    public function afectaEfectivo(): bool
    {
        return $this === self::Cash;
    }
}
