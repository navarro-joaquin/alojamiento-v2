<?php

use App\Enums\BranchRole;
use App\Enums\FixedAssetStatus;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Room;
use App\Models\User;
use App\Services\FixedAssetService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $this->room = Room::factory()->for($this->branch)->create(['room_number' => '204']);
    $this->storekeeper = usuarioDeActivos($this->branch, BranchRole::Storekeeper);
});

function usuarioDeActivos(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeActivo(array $overrides = []): array
{
    return [
        'asset_code' => ' af-0042 ',
        'name' => 'Frigobar',
        'serial_number' => 'FB-889',
        'purchase_date' => '2026-01-20',
        'purchase_cost' => '1450.00',
        'status' => FixedAssetStatus::InUse->value,
        'room_id' => '',
        ...$overrides,
    ];
}

function activoRegistradoEn(Branch $branch): FixedAsset
{
    return app(FixedAssetService::class)->registrar($branch, User::factory()->create(), [
        'asset_code' => 'AF-0001',
        'name' => 'Televisor',
    ], FixedAssetStatus::InStorage);
}

it('registers an asset in a room of the active branch', function () {
    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.store'), datosDeActivo(['room_id' => $this->room->id]))
        ->assertSessionHasNoErrors();

    $fixedAsset = FixedAsset::query()->sole();
    expect($fixedAsset->asset_code)->toBe('AF-0042')
        ->and($fixedAsset->room_id)->toBe($this->room->id)
        ->and($fixedAsset->movements()->count())->toBe(1);

    $this->actingAs($this->storekeeper)
        ->get(route('fixed-assets.index'))
        ->assertOk()
        ->assertSeeInOrder(['AF-0042', 'Frigobar', 'Habitación 204', 'En uso']);
});

it('rejects a room of another branch on registration', function () {
    $otherRoom = Room::factory()->create();

    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.store'), datosDeActivo(['room_id' => $otherRoom->id]))
        ->assertSessionHasErrors('room_id');
});

it('updates only the descriptive data of an asset', function () {
    $fixedAsset = activoRegistradoEn($this->branch);

    $this->actingAs($this->storekeeper)
        ->put(route('fixed-assets.update', $fixedAsset), datosDeActivo(['asset_code' => 'AF-0001', 'name' => 'Televisor 43"', 'status' => 'retired', 'room_id' => $this->room->id]))
        ->assertRedirect(route('fixed-assets.show', $fixedAsset));

    expect($fixedAsset->fresh()->only(['name', 'status', 'room_id']))->toBe(['name' => 'Televisor 43"', 'status' => FixedAssetStatus::InStorage, 'room_id' => null]);
});

it('moves an asset to a room and shows its history', function () {
    $fixedAsset = activoRegistradoEn($this->branch);

    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.movements.store', $fixedAsset), [
            'to_branch_id' => $this->branch->id,
            'to_status' => FixedAssetStatus::InUse->value,
            'to_room_id' => $this->room->id,
            'movement_notes' => 'Instalado',
        ])
        ->assertRedirect(route('fixed-assets.show', $fixedAsset));

    $this->actingAs($this->storekeeper)
        ->get(route('fixed-assets.show', $fixedAsset))
        ->assertOk()
        ->assertSeeInOrder(['Historial', 'Sucursal Centro', 'En depósito', 'Sucursal Centro · Habitación 204', 'En uso', 'Instalado', 'Alta del activo']);
});

it('hands an asset over to another branch, which then manages it', function () {
    $northBranch = Branch::factory()->create();
    $northStorekeeper = usuarioDeActivos($northBranch, BranchRole::Storekeeper);
    $fixedAsset = activoRegistradoEn($this->branch);

    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.movements.store', $fixedAsset), ['to_branch_id' => $northBranch->id, 'to_status' => FixedAssetStatus::InStorage->value])
        ->assertRedirect(route('fixed-assets.index'))
        ->assertSessionHas('status', 'Activo AF-0001 entregado a otra sucursal.');

    $this->actingAs($this->storekeeper)->get(route('fixed-assets.show', $fixedAsset))->assertNotFound();
    $this->actingAs($northStorekeeper)->get(route('fixed-assets.show', $fixedAsset))->assertOk()->assertSee('Sucursal Centro');
});

it('leaves retiring assets to supervisors and admins', function () {
    $fixedAsset = activoRegistradoEn($this->branch);

    $this->actingAs($this->storekeeper)->get(route('fixed-assets.show', $fixedAsset))->assertDontSee('Motivo de la baja');
    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.retire', $fixedAsset), ['retirement_reason' => 'Roto'])
        ->assertForbidden();

    $supervisor = usuarioDeActivos($this->branch, BranchRole::Supervisor);
    $this->actingAs($supervisor)
        ->post(route('fixed-assets.retire', $fixedAsset), ['retirement_reason' => 'Roto sin reparación'])
        ->assertRedirect(route('fixed-assets.show', $fixedAsset));

    expect($fixedAsset->fresh()->status)->toBe(FixedAssetStatus::Retired);

    $this->actingAs($supervisor)
        ->get(route('fixed-assets.index'))
        ->assertSeeInOrder(['Dados de baja', 'AF-0001']);
    $this->actingAs($supervisor)
        ->get(route('fixed-assets.show', $fixedAsset))
        ->assertDontSee('Registrar movimiento')
        ->assertDontSee('Motivo de la baja');
});

it('hides the assets of another branch', function () {
    $otherAsset = activoRegistradoEn(Branch::factory()->create());

    $this->actingAs($this->storekeeper)->get(route('fixed-assets.show', $otherAsset))->assertNotFound();
    $this->actingAs($this->storekeeper)->get(route('fixed-assets.edit', $otherAsset))->assertNotFound();
    $this->actingAs($this->storekeeper)
        ->post(route('fixed-assets.movements.store', $otherAsset), ['to_branch_id' => $this->branch->id, 'to_status' => FixedAssetStatus::InUse->value])
        ->assertNotFound();
});

it('keeps fixed assets away from receptionists', function () {
    $receptionist = usuarioDeActivos($this->branch, BranchRole::Receptionist);

    $this->actingAs($receptionist)->get(route('fixed-assets.index'))->assertForbidden();
    $this->actingAs($receptionist)->post(route('fixed-assets.store'), datosDeActivo())->assertForbidden();
    $this->actingAs($receptionist)->get(route('occupancy.index'))->assertDontSee(route('fixed-assets.index'));
    $this->actingAs($this->storekeeper)->get(route('occupancy.index'))->assertSee(route('fixed-assets.index'));
});
