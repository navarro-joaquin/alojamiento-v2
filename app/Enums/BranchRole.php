<?php

namespace App\Enums;

/**
 * Roles defined globally and assigned to users per branch (spatie teams).
 */
enum BranchRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Receptionist = 'receptionist';
    case Housekeeping = 'housekeeping';
    case Storekeeper = 'storekeeper';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Receptionist => 'Recepcionista',
            self::Housekeeping => 'Limpieza',
            self::Storekeeper => 'Almacenero',
        };
    }

    /**
     * Permissions the role grants in the branch where it is assigned.
     * The storekeeper gets its permissions with inventory (phase 3).
     *
     * @return list<BranchPermission>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Admin => BranchPermission::cases(),
            self::Supervisor => array_values(array_filter(
                BranchPermission::cases(),
                fn (BranchPermission $permission): bool => ! $permission->esDeAdministracion(),
            )),
            self::Receptionist => [
                BranchPermission::CheckInStays,
                BranchPermission::ChangeStayRooms,
                BranchPermission::CheckOutStays,
                BranchPermission::RequestCheckoutExceptions,
                BranchPermission::AddCharges,
                BranchPermission::CollectPayments,
                BranchPermission::RegisterExpenses,
                BranchPermission::OpenCashSessions,
                BranchPermission::CloseCashSessions,
                BranchPermission::UpdateHousekeepingStatus,
            ],
            self::Housekeeping => [
                BranchPermission::UpdateHousekeepingStatus,
            ],
            self::Storekeeper => [],
        };
    }
}
