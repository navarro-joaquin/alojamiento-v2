<?php

use App\Enums\BranchRole;
use App\Http\Middleware\EnsureActiveBranch;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioEnSucursales(Branch ...$branches): User
{
    $user = User::factory()->create();

    foreach ($branches as $branch) {
        $user->asignarRolEnSucursal(BranchRole::Receptionist, $branch->id);
    }

    return $user;
}

it('selects the only assigned branch automatically', function () {
    $branch = Branch::factory()->create(['name' => 'Sucursal Única']);

    $this->actingAs(usuarioEnSucursales($branch))
        ->get(route('occupancy.index'))
        ->assertOk()
        ->assertSee('Sucursal Única')
        ->assertSessionHas(EnsureActiveBranch::SESSION_KEY, $branch->id);
});

it('asks to choose when several branches are assigned', function () {
    $user = usuarioEnSucursales(Branch::factory()->create(['name' => 'Norte']), Branch::factory()->create(['name' => 'Sur']));

    $this->actingAs($user)->get(route('occupancy.index'))->assertRedirect(route('active-branch.edit'));
    $this->actingAs($user)->get(route('active-branch.edit'))->assertOk()->assertSeeInOrder(['Norte', 'Sur']);
});

it('switches to an assigned branch', function () {
    $otherBranch = Branch::factory()->create();
    $user = usuarioEnSucursales(Branch::factory()->create(), $otherBranch);

    $this->actingAs($user)
        ->put(route('active-branch.update'), ['branch_id' => $otherBranch->id])
        ->assertRedirect(route('occupancy.index'))
        ->assertSessionHas(EnsureActiveBranch::SESSION_KEY, $otherBranch->id);
});

it('rejects a branch where the user has no role', function () {
    $user = usuarioEnSucursales(Branch::factory()->create());

    $this->actingAs($user)
        ->put(route('active-branch.update'), ['branch_id' => Branch::factory()->create()->id])
        ->assertSessionHasErrors(['branch_id' => 'No tiene acceso a esa sucursal.']);
});

it('ignores a stale branch kept in the session', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(usuarioEnSucursales($branch))
        ->withSession([EnsureActiveBranch::SESSION_KEY => Branch::factory()->create()->id])
        ->get(route('occupancy.index'))
        ->assertOk()
        ->assertSessionHas(EnsureActiveBranch::SESSION_KEY, $branch->id);
});

it('ignores inactive branches', function () {
    $user = usuarioEnSucursales(Branch::factory()->inactiva()->create());

    $this->actingAs($user)->get(route('occupancy.index'))->assertForbidden();
});

it('logs out a user disabled during the session', function () {
    $user = usuarioEnSucursales(Branch::factory()->create());
    $user->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('occupancy.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
