<?php

use App\Enums\BranchPermission;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function sucursalDelSujeto(Model $subject): int
{
    return match (true) {
        $subject instanceof StayCharge, $subject instanceof Payment => $subject->stay->branch_id,
        $subject instanceof CashSession => $subject->cashRegister->branch_id,
        default => $subject->branch_id,
    };
}

function usuarioConRol(BranchRole $role, int $branchId): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branchId);

    return $user;
}

dataset('habilidades', [
    'room check-in' => ['registrarIngreso', fn () => Room::factory()->create(), BranchPermission::CheckInStays],
    'room housekeeping status' => ['cambiarEstadoLimpieza', fn () => Room::factory()->create(), BranchPermission::UpdateHousekeepingStatus],
    'room operational status' => ['cambiarEstadoOperativo', fn () => Room::factory()->create(), BranchPermission::UpdateOperationalStatus],
    'stay room change' => ['cambiarHabitacion', fn () => Stay::factory()->create(), BranchPermission::ChangeStayRooms],
    'stay check-out' => ['registrarSalida', fn () => Stay::factory()->create(), BranchPermission::CheckOutStays],
    'stay charge' => ['registrarCargo', fn () => Stay::factory()->create(), BranchPermission::AddCharges],
    'stay discount' => ['registrarDescuento', fn () => Stay::factory()->create(), BranchPermission::GrantDiscounts],
    'stay payment' => ['cobrarPago', fn () => Stay::factory()->create(), BranchPermission::CollectPayments],
    'charge void' => ['anular', fn () => StayCharge::factory()->create(), BranchPermission::VoidCharges],
    'payment refund' => ['devolver', fn () => Payment::factory()->create(), BranchPermission::RefundPayments],
    'register session opening' => ['abrirSesion', fn () => CashRegister::factory()->create(), BranchPermission::OpenCashSessions],
    'session closing' => ['cerrar', fn () => CashSession::factory()->create(), BranchPermission::CloseCashSessions],
]);

it('allows each ability only to the roles that grant its permission in the branch', function (string $ability, Closure $crearSujeto, BranchPermission $permission, BranchRole $role) {
    $subject = $crearSujeto();
    $user = usuarioConRol($role, sucursalDelSujeto($subject));

    expect($user->can($ability, $subject))->toBe(in_array($permission, $role->permisos(), true));
})->with('habilidades')->with(BranchRole::cases());

it('denies an ability granted only in another branch', function (string $ability, Closure $crearSujeto) {
    $subject = $crearSujeto();
    $user = usuarioConRol(BranchRole::Admin, Branch::factory()->create()->id);

    expect($user->can($ability, $subject))->toBeFalse();
})->with('habilidades');

it('evaluates the subject branch even when another branch is active', function () {
    $room = Room::factory()->create();
    $activeBranch = Branch::factory()->create();
    $user = usuarioConRol(BranchRole::Admin, $activeBranch->id);
    setPermissionsTeamId($activeBranch->id);

    expect($user->can('registrarIngreso', $room))->toBeFalse()
        ->and(getPermissionsTeamId())->toBe($activeBranch->id);
});

it('denies everything to a disabled user', function (Closure $crearUsuario) {
    $room = Room::factory()->create();
    $user = $crearUsuario();
    $user->asignarRolEnSucursal(BranchRole::Admin, $room->branch_id);

    expect($user->can('registrarIngreso', $room))->toBeFalse();
})->with([
    'inactive user' => fn () => User::factory()->inactivo()->create(),
    'inactive employee' => fn () => User::factory()->for(Employee::factory()->inactivo())->create(),
]);
