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

    public static function denominacionesNoCoinciden(Number $countedAmount, Number $declaredAmount): self
    {
        return new self("El detalle de billetes y monedas suma Bs {$countedAmount}, no Bs {$declaredAmount}.");
    }

    public static function sesionNoPendienteDeRevision(): self
    {
        return new self('La sesión de caja no está pendiente de revisión.');
    }

    public static function revisionPorQuienCerro(): self
    {
        return new self('Quien cerró la caja no puede revisar su propio cierre.');
    }

    public static function estadiaNoActiva(): self
    {
        return new self('La estadía no está activa.');
    }

    public static function estadiaFinalizadaSoloAdmiteSaldo(): self
    {
        return new self('Una estadía finalizada solo admite el cobro de su saldo pendiente, no anticipos.');
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
        return new self("La estadía tiene un saldo pendiente de Bs {$balance} y no hay una salida con deuda autorizada.");
    }

    public static function autorizacionDeSalidaVencida(): self
    {
        return new self('La autorización de salida con deuda venció: solicite una nueva.');
    }

    public static function deudaDistintaDeLaAutorizada(Number $authorizedAmount, Number $balance): self
    {
        return new self("La deuda cambió desde la solicitud (Bs {$authorizedAmount} solicitados, Bs {$balance} actuales): solicite una nueva autorización.");
    }

    public static function salidaRegistradaPorQuienAutorizo(): self
    {
        return new self('Quien autorizó la salida con deuda no puede registrarla: debe hacerlo otro operador.');
    }

    public static function aprobacionPorElSolicitante(): self
    {
        return new self('Quien solicita la salida con deuda no puede autorizarla.');
    }

    public static function estadiaSinDeuda(): self
    {
        return new self('La estadía no tiene saldo pendiente.');
    }

    public static function salidaConDeudaYaAutorizada(): self
    {
        return new self('La estadía ya tiene una salida con deuda autorizada y vigente.');
    }

    public static function solicitudNoPendiente(): self
    {
        return new self('La solicitud de salida con deuda ya no está pendiente.');
    }

    public static function excepcionNoAbierta(): self
    {
        return new self('La salida con deuda ya no está pendiente ni autorizada.');
    }

    public static function gastoEnEfectivoSinSesion(): self
    {
        return new self('Un gasto en efectivo requiere una sesión de caja abierta.');
    }

    public static function efectivoInsuficiente(Number $expectedCash): self
    {
        return new self("El gasto supera el efectivo de la caja (Bs {$expectedCash}).");
    }

    public static function categoriaDeGastoInactiva(): self
    {
        return new self('La categoría de gasto está inactiva.');
    }

    public static function gastoNoPendiente(): self
    {
        return new self('El gasto ya no está pendiente de aprobación.');
    }

    public static function aprobacionDeGastoPorQuienLoRegistro(): self
    {
        return new self('Quien registra un gasto no puede aprobarlo.');
    }

    public static function gastoNoConfirmado(): self
    {
        return new self('Solo se pueden anular gastos confirmados.');
    }

    public static function pagoConReciboVigente(string $receiptNumber): self
    {
        return new self("El pago ya tiene el recibo {$receiptNumber}: anúlelo antes de emitir otro.");
    }

    public static function reciboDePagoNoConfirmado(): self
    {
        return new self('Solo se emiten recibos de pagos confirmados.');
    }

    public static function reciboYaAnulado(): self
    {
        return new self('El recibo ya está anulado.');
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

    public static function almacenInactivo(): self
    {
        return new self('El almacén está inactivo.');
    }

    public static function productoInactivo(): self
    {
        return new self('El producto está inactivo.');
    }

    public static function cantidadNoPositiva(): self
    {
        return new self('La cantidad debe ser mayor que cero.');
    }

    public static function conteoNegativo(): self
    {
        return new self('La cantidad contada no puede ser negativa.');
    }

    public static function costoNegativo(): self
    {
        return new self('El costo unitario no puede ser negativo.');
    }

    public static function existenciasInsuficientes(Number $availableQuantity, string $unit): self
    {
        return new self("No hay existencias suficientes: quedan {$availableQuantity} ({$unit}).");
    }

    public static function tipoDeSalidaNoPermitido(): self
    {
        return new self('Ese tipo de salida no se puede registrar manualmente.');
    }

    public static function ajusteSinDiferencia(): self
    {
        return new self('El conteo coincide con las existencias: no hay nada que ajustar.');
    }
}
