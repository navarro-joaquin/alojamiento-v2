<?php

use App\Enums\BranchPermission;
use App\Enums\BranchRole;

it('keeps sensitive permissions away from the receptionist', function (BranchPermission $permission) {
    expect(BranchRole::Receptionist->permisos())->not->toContain($permission)
        ->and(BranchRole::Supervisor->permisos())->toContain($permission);
})->with([
    'discounts' => BranchPermission::GrantDiscounts,
    'voids' => BranchPermission::VoidCharges,
    'refunds' => BranchPermission::RefundPayments,
    'checkout exception approvals' => BranchPermission::ApproveCheckoutExceptions,
    'operational status' => BranchPermission::UpdateOperationalStatus,
]);

it('keeps branch configuration for the admin', function () {
    $administrationPermissions = array_filter(BranchPermission::cases(), fn (BranchPermission $permission): bool => $permission->esDeAdministracion());

    expect($administrationPermissions)->not->toBeEmpty()
        ->and(BranchRole::Admin->permisos())->toContain(...$administrationPermissions);

    foreach ([BranchRole::Supervisor, BranchRole::Receptionist, BranchRole::Housekeeping, BranchRole::Storekeeper] as $role) {
        expect(array_intersect(array_map(fn ($permission) => $permission->value, $role->permisos()), array_map(fn ($permission) => $permission->value, $administrationPermissions)))->toBeEmpty();
    }
});

it('limits housekeeping to the housekeeping status', function () {
    expect(BranchRole::Housekeeping->permisos())->toBe([BranchPermission::UpdateHousekeepingStatus]);
});
