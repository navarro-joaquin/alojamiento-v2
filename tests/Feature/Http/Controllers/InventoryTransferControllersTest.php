<?php

use App\Enums\BranchRole;
use App\Enums\InventoryTransferStatus;
use App\Http\Middleware\EnsureActiveBranch;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->centralBranch = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $this->northBranch = Branch::factory()->create(['name' => 'Sucursal Norte']);
    $this->depot = Warehouse::factory()->for($this->centralBranch)->create(['name' => 'Depósito']);
    $this->northMinibar = Warehouse::factory()->for($this->northBranch)->create(['name' => 'Minibar']);
    $this->water = Product::factory()->create(['name' => 'Agua', 'unit' => 'botella']);
    InventoryBalance::factory()->for($this->depot)->for($this->water)->create(['quantity' => '30.000', 'average_cost' => '4.0000']);
    $this->centralStorekeeper = almaceneroDe($this->centralBranch);
    $this->northStorekeeper = almaceneroDe($this->northBranch);
});

function almaceneroDe(Branch $branch): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal(BranchRole::Storekeeper, $branch->id);

    return $user;
}

function transferenciaEnviada(): InventoryTransfer
{
    $transfer = InventoryTransfer::factory()->create([
        'from_warehouse_id' => test()->depot->id,
        'to_warehouse_id' => test()->northMinibar->id,
    ]);
    $transfer->items()->create(['product_id' => test()->water->id, 'quantity' => '10']);
    app(InventoryTransferService::class)->enviar($transfer, test()->centralStorekeeper);

    return $transfer;
}

it('creates a draft from the rows with a product and sends it', function () {
    $this->actingAs($this->centralStorekeeper)
        ->post(route('inventory-transfers.store'), [
            'from_warehouse_id' => $this->depot->id,
            'to_warehouse_id' => $this->northMinibar->id,
            'notes' => 'Reposición',
            'items' => [
                ['product_id' => $this->water->id, 'quantity' => '12'],
                ['product_id' => '', 'quantity' => ''],
            ],
        ])
        ->assertSessionHasNoErrors();

    $transfer = InventoryTransfer::query()->sole();
    expect($transfer->status)->toBe(InventoryTransferStatus::Draft)
        ->and($transfer->items()->pluck('quantity', 'product_id')->all())->toBe([$this->water->id => '12.000']);

    $this->actingAs($this->centralStorekeeper)
        ->get(route('inventory-transfers.show', $transfer))
        ->assertOk()
        ->assertSee('Borrador')
        ->assertSee('Enviar transferencia');

    $this->actingAs($this->centralStorekeeper)
        ->post(route('inventory-transfers.send', $transfer))
        ->assertRedirect(route('inventory-transfers.show', $transfer));

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Sent);
});

it('only creates transfers from a warehouse of the active branch', function () {
    $this->actingAs($this->northStorekeeper)
        ->post(route('inventory-transfers.store'), [
            'from_warehouse_id' => $this->depot->id,
            'to_warehouse_id' => $this->northMinibar->id,
            'items' => [['product_id' => $this->water->id, 'quantity' => '1']],
        ])
        ->assertSessionHasErrors('from_warehouse_id');
});

it('shows the rejection of an empty transfer on the form', function () {
    $this->actingAs($this->centralStorekeeper)
        ->post(route('inventory-transfers.store'), [
            'from_warehouse_id' => $this->depot->id,
            'to_warehouse_id' => $this->northMinibar->id,
            'items' => [['product_id' => '', 'quantity' => '']],
        ])
        ->assertSessionHasErrors(['operation' => 'La transferencia no tiene productos.']);
});

it('lets the destination branch receive a transfer in transit', function () {
    $transfer = transferenciaEnviada();

    $this->actingAs($this->northStorekeeper)
        ->get(route('inventory-transfers.index'))
        ->assertOk()
        ->assertSeeInOrder(['Por recibir', "#{$transfer->id}", 'desde Sucursal Centro · Depósito']);

    $this->actingAs($this->northStorekeeper)
        ->get(route('inventory-transfers.show', $transfer))
        ->assertSee('Recibir transferencia')
        ->assertDontSee('Enviar transferencia');

    $this->actingAs($this->northStorekeeper)
        ->post(route('inventory-transfers.receive', $transfer), [
            'received' => [$this->water->id => '9'],
            'reception_notes' => 'Una botella rota',
        ])
        ->assertRedirect(route('inventory-transfers.show', $transfer));

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Received)
        ->and(InventoryBalance::query()->where('warehouse_id', $this->northMinibar->id)->value('quantity'))->toBe('9.000');
});

it('does not let the source branch receive its own transfer', function () {
    $transfer = transferenciaEnviada();

    $this->actingAs($this->centralStorekeeper)
        ->get(route('inventory-transfers.show', $transfer))
        ->assertOk()
        ->assertDontSee('Recibir transferencia');

    $this->actingAs($this->centralStorekeeper)
        ->post(route('inventory-transfers.receive', $transfer), ['received' => [$this->water->id => '10']])
        ->assertNotFound();
});

it('does not let the destination branch send or cancel a draft', function () {
    $transfer = InventoryTransfer::factory()->create(['from_warehouse_id' => $this->depot->id, 'to_warehouse_id' => $this->northMinibar->id]);

    $this->actingAs($this->northStorekeeper)->post(route('inventory-transfers.send', $transfer))->assertNotFound();
    $this->actingAs($this->northStorekeeper)->post(route('inventory-transfers.cancel', $transfer))->assertNotFound();

    $this->actingAs($this->centralStorekeeper)
        ->post(route('inventory-transfers.cancel', $transfer))
        ->assertRedirect(route('inventory-transfers.show', $transfer));

    expect($transfer->fresh()->status)->toBe(InventoryTransferStatus::Cancelled);
});

it('hides transfers that do not involve the active branch', function () {
    $southBranch = Branch::factory()->create();
    $southStorekeeper = almaceneroDe($southBranch);
    $transfer = transferenciaEnviada();

    $this->actingAs($southStorekeeper)->get(route('inventory-transfers.show', $transfer))->assertNotFound();
    $this->actingAs($southStorekeeper)->get(route('inventory-transfers.index'))->assertOk()->assertDontSee("#{$transfer->id}");
});

it('keeps transfers away from receptionists', function () {
    $receptionist = User::factory()->create();
    $receptionist->asignarRolEnSucursal(BranchRole::Receptionist, $this->centralBranch->id);

    $this->actingAs($receptionist)->get(route('inventory-transfers.index'))->assertForbidden();
    $this->actingAs($receptionist)->get(route('occupancy.index'))->assertDontSee(route('inventory-transfers.index'));
    $this->actingAs($this->centralStorekeeper)->get(route('occupancy.index'))->assertSee(route('inventory-transfers.index'));
});

it('works on the branch chosen by a user of several branches', function () {
    $supervisor = User::factory()->create();
    $supervisor->asignarRolEnSucursal(BranchRole::Supervisor, $this->centralBranch->id);
    $supervisor->asignarRolEnSucursal(BranchRole::Supervisor, $this->northBranch->id);
    $transfer = transferenciaEnviada();

    $this->actingAs($supervisor)
        ->withSession([EnsureActiveBranch::SESSION_KEY => $this->centralBranch->id])
        ->post(route('inventory-transfers.receive', $transfer), ['received' => [$this->water->id => '10']])
        ->assertNotFound();

    $this->actingAs($supervisor)
        ->withSession([EnsureActiveBranch::SESSION_KEY => $this->northBranch->id])
        ->post(route('inventory-transfers.receive', $transfer), ['received' => [$this->water->id => '10']])
        ->assertRedirect(route('inventory-transfers.show', $transfer));
});
