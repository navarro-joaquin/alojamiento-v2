<?php

namespace App\Enums;

/**
 * Permissions granted through roles assigned per branch (spatie teams).
 */
enum BranchPermission: string
{
    case CheckInStays = 'stays.check_in';
    case ChangeStayRooms = 'stays.change_room';
    case CheckOutStays = 'stays.check_out';
    case AddCharges = 'charges.add';
    case GrantDiscounts = 'charges.discount';
    case VoidCharges = 'charges.void';
    case CollectPayments = 'payments.collect';
    case RefundPayments = 'payments.refund';
    case OpenCashSessions = 'cash_sessions.open';
    case CloseCashSessions = 'cash_sessions.close';
    case UpdateHousekeepingStatus = 'rooms.update_housekeeping';
    case UpdateOperationalStatus = 'rooms.update_operational';
    case ManageRooms = 'rooms.manage';
    case ManageRoomRates = 'room_rates.manage';
    case ManageCashRegisters = 'cash_registers.manage';
    case ManageStaff = 'staff.manage';

    /**
     * Whether the permission configures the branch rather than operating it.
     */
    public function esDeAdministracion(): bool
    {
        return in_array($this, [self::ManageRooms, self::ManageRoomRates, self::ManageCashRegisters, self::ManageStaff], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::CheckInStays => 'Registrar ingresos',
            self::ChangeStayRooms => 'Cambiar de habitación',
            self::CheckOutStays => 'Registrar salidas',
            self::AddCharges => 'Registrar cargos',
            self::GrantDiscounts => 'Aplicar descuentos',
            self::VoidCharges => 'Anular cargos',
            self::CollectPayments => 'Cobrar pagos',
            self::RefundPayments => 'Devolver pagos',
            self::OpenCashSessions => 'Abrir caja',
            self::CloseCashSessions => 'Cerrar caja',
            self::UpdateHousekeepingStatus => 'Cambiar estado de limpieza',
            self::UpdateOperationalStatus => 'Cambiar estado operativo',
            self::ManageRooms => 'Gestionar habitaciones',
            self::ManageRoomRates => 'Gestionar tarifas',
            self::ManageCashRegisters => 'Gestionar cajas',
            self::ManageStaff => 'Gestionar personal',
        };
    }
}
