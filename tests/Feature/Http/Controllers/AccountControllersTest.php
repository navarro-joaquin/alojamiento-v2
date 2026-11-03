<?php

use App\Enums\BranchPermission;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->superAdmin = User::factory()->superAdministrador()->create();
});

function miembroDelPersonal(Branch $branch, BranchRole $role = BranchRole::Admin): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('lets only a super admin manage accounts', function () {
    $branchAdmin = miembroDelPersonal($this->branch);

    $this->actingAs($branchAdmin)->get(route('users.index'))->assertForbidden();
    $this->actingAs($branchAdmin)->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secreta-123', 'password_confirmation' => 'secreta-123'])->assertForbidden();
    $this->actingAs($branchAdmin)->get(route('occupancy.index'))->assertDontSee(route('users.index'));

    $this->actingAs($this->superAdmin)->get(route('users.index'))->assertOk()->assertSee($branchAdmin->email);
    $this->actingAs($this->superAdmin)->get(route('users.create'))->assertOk();
});

it('creates an account and audits it without the password', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('users.store'), [
            'name' => 'Ana Rojas',
            'email' => ' Ana@Example.com ',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
            'is_active' => '1',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'ana@example.com')->sole();
    expect($user->is_active)->toBeTrue()
        ->and($user->is_super_admin)->toBeFalse()
        ->and(Hash::check('secreta-123', $user->password))->toBeTrue();

    $activity = Activity::query()->whereMorphedTo('subject', $user)->sole();
    expect($activity->event)->toBe('user.created')
        ->and($activity->properties->except(['request_id', 'ip_address'])->all())->toBe(['email' => 'ana@example.com']);
});

it('requires a confirmed password only when creating', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('users.store'), ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secreta-123', 'password_confirmation' => 'otra'])
        ->assertSessionHasErrors('password');

    $user = User::factory()->create(['password' => 'actual-123']);

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), ['name' => 'Nombre nuevo', 'email' => $user->email, 'password' => '', 'password_confirmation' => '', 'is_active' => '1'])
        ->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->name)->toBe('Nombre nuevo')->and(Hash::check('actual-123', $user->password))->toBeTrue();
    expect(Activity::query()->whereMorphedTo('subject', $user)->sole()->properties['password_changed'])->toBeFalse();
});

it('changes the password and disables the account', function () {
    $user = User::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'password' => 'nueva-1234', 'password_confirmation' => 'nueva-1234', 'is_active' => '0'])
        ->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->is_active)->toBeFalse()->and(Hash::check('nueva-1234', $user->password))->toBeTrue();

    $properties = Activity::query()->whereMorphedTo('subject', $user)->sole()->properties;
    expect($properties['password_changed'])->toBeTrue()
        ->and($properties['changes'])->toBe(['is_active' => false]);
});

it('keeps emails unique and the super admin flag out of the form', function () {
    $other = User::factory()->create(['email' => 'ocupado@example.com']);
    $user = User::factory()->create();

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), ['name' => $user->name, 'email' => 'OCUPADO@example.com', 'is_active' => '1'])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'is_active' => '1', 'is_super_admin' => '1'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->is_super_admin)->toBeFalse()->and($other->refresh()->email)->toBe('ocupado@example.com');
});

it('does not let a super admin disable their own account', function () {
    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $this->superAdmin), ['name' => $this->superAdmin->name, 'email' => $this->superAdmin->email, 'is_active' => '0'])
        ->assertSessionHasErrors('is_active');

    expect($this->superAdmin->refresh()->is_active)->toBeTrue();
});

it('lists the staff of the active branch only', function () {
    $admin = miembroDelPersonal($this->branch);
    $receptionist = miembroDelPersonal($this->branch, BranchRole::Receptionist);
    $outsider = miembroDelPersonal(Branch::factory()->create());

    $this->actingAs($admin)
        ->get(route('staff-members.index'))
        ->assertOk()
        ->assertSee($receptionist->email)
        ->assertSee('Recepcionista')
        ->assertDontSee($outsider->email)
        ->assertSee(route('staff-members.destroy', $receptionist))
        ->assertDontSee(route('staff-members.destroy', $admin));

    $this->actingAs($admin)->get(route('occupancy.index'))->assertSee(route('staff-members.index'));
});

it('forbids staff management without the staff permission', function () {
    $supervisor = miembroDelPersonal($this->branch, BranchRole::Supervisor);
    $receptionist = miembroDelPersonal($this->branch, BranchRole::Receptionist);

    $this->actingAs($supervisor)->get(route('staff-members.index'))->assertForbidden();
    $this->actingAs($supervisor)->post(route('staff-members.store'), ['email' => $supervisor->email, 'role' => 'admin'])->assertForbidden();
    $this->actingAs($supervisor)->delete(route('staff-members.destroy', $receptionist))->assertForbidden();
});

it('assigns a single role per branch without touching other branches', function () {
    $admin = miembroDelPersonal($this->branch);
    $otherBranch = Branch::factory()->create();
    $member = miembroDelPersonal($otherBranch, BranchRole::Supervisor);

    $this->actingAs($admin)
        ->post(route('staff-members.store'), ['email' => strtoupper($member->email), 'role' => 'housekeeping'])
        ->assertRedirect(route('staff-members.index'));

    $this->actingAs($admin)
        ->post(route('staff-members.store'), ['email' => $member->email, 'role' => 'receptionist'])
        ->assertRedirect(route('staff-members.index'));

    expect($member->rolesEnSucursal($this->branch->id))->toBe(['receptionist'])
        ->and($member->rolesEnSucursal($otherBranch->id))->toBe(['supervisor'])
        ->and($member->tienePermisoEnSucursal(BranchPermission::CollectPayments, $this->branch->id))->toBeTrue()
        ->and($member->tienePermisoEnSucursal(BranchPermission::UpdateHousekeepingStatus, $this->branch->id))->toBeTrue();

    $activity = Activity::query()->whereMorphedTo('subject', $member)->where('event', 'staff.role_assigned')->latest('id')->first();
    expect($activity->properties->only(['role', 'previous_roles'])->all())->toBe(['role' => 'receptionist', 'previous_roles' => ['housekeeping']]);
});

it('only assigns roles to existing accounts and never to oneself', function () {
    $admin = miembroDelPersonal($this->branch);

    $this->actingAs($admin)
        ->post(route('staff-members.store'), ['email' => 'nadie@example.com', 'role' => 'receptionist'])
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)
        ->post(route('staff-members.store'), ['email' => $admin->email, 'role' => 'receptionist'])
        ->assertSessionHasErrors('email');

    $this->actingAs($admin)
        ->post(route('staff-members.store'), ['email' => User::factory()->create()->email, 'role' => 'owner'])
        ->assertSessionHasErrors('role');

    expect($admin->rolesEnSucursal($this->branch->id))->toBe(['admin']);
});

it('removes a member from the branch only', function () {
    $admin = miembroDelPersonal($this->branch);
    $otherBranch = Branch::factory()->create();
    $member = miembroDelPersonal($this->branch, BranchRole::Receptionist);
    $member->asignarRolEnSucursal(BranchRole::Supervisor, $otherBranch->id);

    $this->actingAs($admin)
        ->delete(route('staff-members.destroy', $member))
        ->assertRedirect(route('staff-members.index'));

    expect($member->rolesEnSucursal($this->branch->id))->toBe([])
        ->and($member->rolesEnSucursal($otherBranch->id))->toBe(['supervisor'])
        ->and($member->sucursalesAsignadas()->modelKeys())->toBe([$otherBranch->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'staff.roles_removed', 'subject_id' => $member->id, 'branch_id' => $this->branch->id]);

    $this->actingAs($admin)->delete(route('staff-members.destroy', $member))->assertNotFound();
});

it('does not let an admin remove themselves', function () {
    $admin = miembroDelPersonal($this->branch);

    $this->actingAs($admin)
        ->delete(route('staff-members.destroy', $admin))
        ->assertSessionHasErrors('operation');

    expect($admin->rolesEnSucursal($this->branch->id))->toBe(['admin']);
});
