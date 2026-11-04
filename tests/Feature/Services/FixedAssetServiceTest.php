<?php

use App\Enums\FixedAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AssetMovement;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\Room;
use App\Models\User;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->room = Room::factory()->for($this->branch)->create(['room_number' => '201']);
    $this->user = User::factory()->create();
});

function registrarActivo(FixedAssetStatus $status = FixedAssetStatus::InUse, ?Room $room = null): FixedAsset
{
    $test = test();

    return app(FixedAssetService::class)->registrar($test->branch, $test->user, [
        'asset_code' => 'AF-00010',
        'name' => 'Televisor 32"',
        'serial_number' => 'SN-123',
        'purchase_date' => '2026-03-10',
        'purchase_cost' => '2100.00',
    ], $status, $room);
}

it('registers an asset with its first movement', function () {
    $fixedAsset = registrarActivo(room: $this->room);

    $movement = $fixedAsset->movements()->sole();
    expect($fixedAsset->only(['branch_id', 'room_id', 'status', 'purchase_cost']))->toBe([
        'branch_id' => $this->branch->id,
        'room_id' => $this->room->id,
        'status' => FixedAssetStatus::InUse,
        'purchase_cost' => '2100.00',
    ])
        ->and($movement->only(['from_branch_id', 'to_branch_id', 'to_room_id', 'from_status', 'to_status', 'notes']))->toBe([
            'from_branch_id' => null,
            'to_branch_id' => $this->branch->id,
            'to_room_id' => $this->room->id,
            'from_status' => null,
            'to_status' => FixedAssetStatus::InUse,
            'notes' => 'Alta del activo',
        ]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'assets', 'event' => 'fixed_asset.registered', 'subject_id' => $fixedAsset->id]);
});

it('moves an asset between rooms and records where it came from', function () {
    $fixedAsset = registrarActivo(room: $this->room);
    $otherRoom = Room::factory()->for($this->branch)->create();

    app(FixedAssetService::class)->mover($fixedAsset, $this->user, $this->branch, FixedAssetStatus::InUse, $otherRoom, 'Cambio de habitación');

    $movement = $fixedAsset->movements()->latest('id')->first();
    expect($fixedAsset->fresh()->room_id)->toBe($otherRoom->id)
        ->and($movement->only(['from_room_id', 'to_room_id', 'from_status', 'to_status', 'notes']))->toBe([
            'from_room_id' => $this->room->id,
            'to_room_id' => $otherRoom->id,
            'from_status' => FixedAssetStatus::InUse,
            'to_status' => FixedAssetStatus::InUse,
            'notes' => 'Cambio de habitación',
        ]);
    $this->assertDatabaseHas('activity_log', ['event' => 'fixed_asset.moved', 'subject_id' => $fixedAsset->id]);
});

it('takes the asset out of its room when it goes to repair', function () {
    $fixedAsset = registrarActivo(room: $this->room);

    app(FixedAssetService::class)->mover($fixedAsset, $this->user, $this->branch, FixedAssetStatus::InRepair, notes: 'No enciende');

    expect($fixedAsset->fresh()->only(['room_id', 'status']))->toBe(['room_id' => null, 'status' => FixedAssetStatus::InRepair]);
});

it('hands an asset over to another branch', function () {
    $fixedAsset = registrarActivo(FixedAssetStatus::InStorage);
    $northBranch = Branch::factory()->create();

    app(FixedAssetService::class)->mover($fixedAsset, $this->user, $northBranch, FixedAssetStatus::InStorage);

    $movement = $fixedAsset->movements()->latest('id')->first();
    expect($fixedAsset->fresh()->branch_id)->toBe($northBranch->id)
        ->and($movement->only(['from_branch_id', 'to_branch_id']))->toBe(['from_branch_id' => $this->branch->id, 'to_branch_id' => $northBranch->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'fixed_asset.moved', 'subject_id' => $fixedAsset->id, 'branch_id' => $this->branch->id]);
});

it('only places an asset in use in a room of its branch', function () {
    $otherBranchRoom = Room::factory()->create();

    expect(fn () => registrarActivo(FixedAssetStatus::InStorage, $this->room))
        ->toThrow(BusinessRuleException::class, 'Solo un activo en uso se ubica en una habitación.')
        ->and(fn () => registrarActivo(FixedAssetStatus::InUse, $otherBranchRoom))
        ->toThrow(BusinessRuleException::class, 'La habitación pertenece a otra sucursal.');

    $fixedAsset = registrarActivo(FixedAssetStatus::InStorage);
    $northBranch = Branch::factory()->create();

    expect(fn () => app(FixedAssetService::class)->mover($fixedAsset, $this->user, $northBranch, FixedAssetStatus::InUse, $this->room))
        ->toThrow(BusinessRuleException::class, 'La habitación pertenece a otra sucursal.');
});

it('rejects a move that changes nothing or goes to an inactive branch', function () {
    $fixedAsset = registrarActivo(room: $this->room);

    expect(fn () => app(FixedAssetService::class)->mover($fixedAsset, $this->user, $this->branch, FixedAssetStatus::InUse, $this->room))
        ->toThrow(BusinessRuleException::class, 'El activo ya está en esa ubicación y estado.')
        ->and(fn () => app(FixedAssetService::class)->mover($fixedAsset, $this->user, Branch::factory()->inactiva()->create(), FixedAssetStatus::InStorage))
        ->toThrow(BusinessRuleException::class, 'La sucursal está inactiva.');
});

it('retires an asset for good with its reason', function () {
    $fixedAsset = registrarActivo(room: $this->room);

    app(FixedAssetService::class)->darDeBaja($fixedAsset, $this->user, 'Pantalla rota sin reparación');

    expect($fixedAsset->fresh()->only(['room_id', 'status']))->toBe(['room_id' => null, 'status' => FixedAssetStatus::Retired])
        ->and(AssetMovement::query()->latest('id')->first()->notes)->toBe('Pantalla rota sin reparación');
    $this->assertDatabaseHas('activity_log', ['event' => 'fixed_asset.retired', 'subject_id' => $fixedAsset->id]);

    expect(fn () => app(FixedAssetService::class)->mover($fixedAsset, $this->user, $this->branch, FixedAssetStatus::InStorage))
        ->toThrow(BusinessRuleException::class, 'El activo ya está dado de baja.')
        ->and(fn () => app(FixedAssetService::class)->darDeBaja($fixedAsset, $this->user, 'Otra vez'))
        ->toThrow(BusinessRuleException::class, 'El activo ya está dado de baja.');
});

it('only retires through its own operation', function () {
    $fixedAsset = registrarActivo();

    expect(fn () => registrarActivo(FixedAssetStatus::Retired))
        ->toThrow(BusinessRuleException::class, 'Para dar de baja un activo use la opción de baja, con su motivo.')
        ->and(fn () => app(FixedAssetService::class)->mover($fixedAsset, $this->user, $this->branch, FixedAssetStatus::Retired))
        ->toThrow(BusinessRuleException::class, 'Para dar de baja un activo use la opción de baja, con su motivo.');
});
