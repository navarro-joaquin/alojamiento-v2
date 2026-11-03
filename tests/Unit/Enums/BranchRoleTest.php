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
    'operational status' => BranchPermission::UpdateOperationalStatus,
]);

it('limits housekeeping to the housekeeping status', function () {
    expect(BranchRole::Housekeeping->permisos())->toBe([BranchPermission::UpdateHousekeepingStatus]);
});
