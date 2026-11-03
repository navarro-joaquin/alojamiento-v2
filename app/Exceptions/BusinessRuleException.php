<?php

namespace App\Exceptions;

use BcMath\Number;
use Exception;

/**
 * A domain operation was rejected because it would break a business rule.
 * Messages are written for the operator and may be shown in the interface.
 */
class BusinessRuleException extends Exception
{
    public static function cajaInactiva(): self
    {
        return new self('La caja está inactiva.');
    }

    public static function cajaConSesionAbierta(): self
    {
        return new self('La caja ya tiene una sesión abierta.');
    }

    public static function sesionNoAbierta(): self
    {
        return new self('La sesión de caja no está abierta.');
    }

    public static function sesionDeOtraSucursal(): self
    {
        return new self('La sesión de caja pertenece a otra sucursal.');
    }

    public static function estadiaNoActiva(): self
    {
        return new self('La estadía no está activa.');
    }

    public static function importeNoPositivo(): self
    {
        return new self('El importe debe ser mayor que cero.');
    }

    public static function importeNegativo(): self
    {
        return new self('El importe no puede ser negativo.');
    }

    public static function pagoSuperaSaldo(Number $balance): self
    {
        return new self("El pago supera el saldo pendiente (Bs {$balance}).");
    }

    public static function anticipoSuperaTope(Number $maximumAmount): self
    {
        return new self("El anticipo supera el máximo permitido (Bs {$maximumAmount}).");
    }

    public static function pagoNoConfirmado(): self
    {
        return new self('Solo se pueden devolver pagos confirmados.');
    }

    public static function devolucionSuperaReembolsable(Number $refundableAmount): self
    {
        return new self("La devolución supera el importe reembolsable (Bs {$refundableAmount}).");
    }

    public static function devolucionSuperaSaldoAFavor(Number $creditAmount): self
    {
        return new self("La devolución supera el saldo a favor del huésped (Bs {$creditAmount}). Anule o descuente primero lo cobrado de más.");
    }

    public static function devolucionEnEfectivoSinSesion(): self
    {
        return new self('Una devolución en efectivo requiere una sesión de caja abierta.');
    }

    public static function sinTarifaAplicable(): self
    {
        return new self('No hay una tarifa vigente para este tipo de habitación.');
    }

    public static function tarifaSolapada(): self
    {
        return new self('Ya existe una tarifa con la misma prioridad que se solapa en fechas, ocupación y días.');
    }

    public static function habitacionNoDisponible(): self
    {
        return new self('La habitación no está disponible: debe estar en servicio y limpia.');
    }

    public static function habitacionOcupada(): self
    {
        return new self('La habitación ya está ocupada.');
    }

    public static function habitacionDeOtraSucursal(): self
    {
        return new self('La habitación pertenece a otra sucursal.');
    }

    public static function mismaHabitacion(): self
    {
        return new self('La estadía ya está en esa habitación.');
    }

    public static function capacidadExcedida(int $capacity): self
    {
        return new self("La habitación admite como máximo {$capacity} huéspedes.");
    }

    public static function huespedRepetido(): self
    {
        return new self('Un huésped no puede figurar dos veces en la misma estadía.');
    }

    public static function salidaConDeuda(Number $balance): self
    {
        return new self("La estadía tiene un saldo pendiente de Bs {$balance}.");
    }

    public static function saldoAFavorNoReembolsable(Number $remainingAmount): self
    {
        return new self("No hay pagos suficientes para devolver el saldo a favor (faltan Bs {$remainingAmount}).");
    }

    public static function tipoDeCargoNoPermitido(): self
    {
        return new self('Ese tipo de cargo no se puede registrar manualmente.');
    }

    public static function cargoNoConfirmado(): self
    {
        return new self('El cargo ya está anulado.');
    }

    public static function cierreDiarioNoCorresponde(): self
    {
        return new self('La fecha de negocio ya está al día; no corresponde cerrarla todavía.');
    }
}
