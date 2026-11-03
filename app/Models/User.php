<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BranchPermission;
use App\Enums\BranchRole;
use App\Enums\EmployeeStatus;
use Closure;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['employee_id', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Mirrors the column default, so a user created in memory is not
     * mistaken for a disabled one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_super_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * An inactive user, or one linked to an inactive employee, cannot operate.
     */
    public function estaHabilitado(): bool
    {
        return $this->is_active && ($this->employee === null || $this->employee->status === EmployeeStatus::Active);
    }

    /**
     * Checks the permission against the roles assigned in the given branch,
     * regardless of the branch currently active in the session.
     */
    public function tienePermisoEnSucursal(BranchPermission $permission, int $branchId): bool
    {
        return $this->enSucursal($branchId, fn (): bool => $this->checkPermissionTo($permission));
    }

    /**
     * Active branches where the user may work: those where the user holds at
     * least one role, or all of them for a super admin.
     *
     * @return Collection<int, Branch>
     */
    public function sucursalesAsignadas(): Collection
    {
        if ($this->is_super_admin) {
            return Branch::query()->where('is_active', true)->orderBy('name')->get();
        }

        $assignedBranchIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->id)
            ->select(config('permission.column_names.team_foreign_key'));

        return Branch::query()
            ->where('is_active', true)
            ->whereIn('id', $assignedBranchIds)
            ->orderBy('name')
            ->get();
    }

    public function asignarRolEnSucursal(BranchRole $role, int $branchId): void
    {
        $this->enSucursal($branchId, fn () => $this->assignRole($role));
    }

    /**
     * Leaves the user with exactly this role in the branch, without touching
     * the roles held in other branches.
     */
    public function cambiarRolEnSucursal(BranchRole $role, int $branchId): void
    {
        $this->enSucursal($branchId, fn () => $this->syncRoles($role));
    }

    public function quitarRolesEnSucursal(int $branchId): void
    {
        $this->enSucursal($branchId, fn () => $this->syncRoles([]));
    }

    /**
     * @return list<string>
     */
    public function rolesEnSucursal(int $branchId): array
    {
        return $this->enSucursal($branchId, fn (): array => $this->roles->pluck('name')->all());
    }

    /**
     * Runs the callback with the branch as the active permissions team and
     * restores the previous team afterwards. The loaded roles always belong
     * to the active team, so they are reused when the branch is already active.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function enSucursal(int $branchId, Closure $callback): mixed
    {
        $previousTeamId = getPermissionsTeamId();

        if ($previousTeamId === $branchId) {
            return $callback();
        }

        setPermissionsTeamId($branchId);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousTeamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
