<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Sale = 'sale';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Exit => 'Salida',
            self::Sale => 'Venta',
            self::Consumption => 'Consumo interno',
            self::Adjustment => 'Ajuste',
            self::TransferIn => 'Transferencia recibida',
            self::TransferOut => 'Transferencia enviada',
        };
    }

    /**
     * The direction every movement of the type has; null for adjustments,
     * which go either way.
     */
    public function direccion(): ?InventoryMovementDirection
    {
        return match ($this) {
            self::Entry, self::TransferIn => InventoryMovementDirection::In,
            self::Exit, self::Sale, self::Consumption, self::TransferOut => InventoryMovementDirection::Out,
            self::Adjustment => null,
        };
    }

    /**
     * Types registered by hand as an exit from the inventory screen.
     *
     * @return list<self>
     */
    public static function salidasManuales(): array
    {
        return [self::Exit, self::Consumption];
    }
}
