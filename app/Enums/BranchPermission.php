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
    case VoidReceipts = 'receipts.void';
    case RequestCheckoutExceptions = 'checkout_exceptions.request';
    case ApproveCheckoutExceptions = 'checkout_exceptions.approve';
    case RegisterExpenses = 'expenses.register';
    case ApproveExpenses = 'expenses.approve';
    case VoidExpenses = 'expenses.void';
    case OpenCashSessions = 'cash_sessions.open';
    case CloseCashSessions = 'cash_sessions.close';
    case ReviewCashSessions = 'cash_sessions.review';
    case UpdateHousekeepingStatus = 'rooms.update_housekeeping';
    case UpdateOperationalStatus = 'rooms.update_operational';
    case ViewInventory = 'inventory.view';
    case RegisterInventoryMovements = 'inventory.register';
    case AdjustInventory = 'inventory.adjust';
    case ViewReports = 'reports.view';
    case ViewAuditLog = 'audit.view';
    case ManageRooms = 'rooms.manage';
    case ManageRoomRates = 'room_rates.manage';
    case ManageCashRegisters = 'cash_registers.manage';
    case ManageStaff = 'staff.manage';
    case ManageWarehouses = 'warehouses.manage';

    /**
     * Whether the permission is reserved to the branch admin: it configures or
     * oversees the branch rather than operating it.
     */
    public function esDeAdministracion(): bool
    {
        return in_array($this, [self::ViewAuditLog, self::ManageRooms, self::ManageRoomRates, self::ManageCashRegisters, self::ManageStaff, self::ManageWarehouses], true);
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
            self::VoidReceipts => 'Anular recibos',
            self::RequestCheckoutExceptions => 'Solicitar salidas con deuda',
            self::ApproveCheckoutExceptions => 'Autorizar salidas con deuda',
            self::RegisterExpenses => 'Registrar gastos',
            self::ApproveExpenses => 'Aprobar gastos',
            self::VoidExpenses => 'Anular gastos',
            self::OpenCashSessions => 'Abrir caja',
            self::CloseCashSessions => 'Cerrar caja',
            self::ReviewCashSessions => 'Revisar cierres de caja',
            self::UpdateHousekeepingStatus => 'Cambiar estado de limpieza',
            self::UpdateOperationalStatus => 'Cambiar estado operativo',
            self::ViewInventory => 'Consultar el inventario',
            self::RegisterInventoryMovements => 'Registrar entradas y salidas de inventario',
            self::AdjustInventory => 'Ajustar el inventario',
            self::ViewReports => 'Ver reportes financieros',
            self::ViewAuditLog => 'Consultar la auditoría',
            self::ManageRooms => 'Gestionar habitaciones',
            self::ManageRoomRates => 'Gestionar tarifas',
            self::ManageCashRegisters => 'Gestionar cajas',
            self::ManageStaff => 'Gestionar personal',
            self::ManageWarehouses => 'Gestionar almacenes',
        };
    }
}
