<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case Expense = 'expense';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Payment => 'Cobro',
            self::Refund => 'Devolución',
            self::Expense => 'Gasto',
            self::Deposit => 'Ingreso a caja',
            self::Withdrawal => 'Retiro de caja',
            self::Adjustment => 'Ajuste',
        };
    }
}
