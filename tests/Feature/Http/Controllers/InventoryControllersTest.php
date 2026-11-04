<?php

use App\Enums\BranchRole;
use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->warehouse = Warehouse::factory()->for($this->branch)->create(['name' => 'Depósito']);
    $this->product = Product::factory()->create(['name' => 'Jabón de tocador', 'unit' => 'pieza']);
    $this->storekeeper = usuarioDeInventario($this->branch, BranchRole::Storekeeper);
});

function usuarioDeInventario(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeEntrada(Product $product, array $overrides = []): array
{
    return [
        'entry_product_id' => $product->id,
        'entry_quantity' => '50',
        'entry_unit_cost' => '2.40',
        'entry_reference' => 'Factura 881',
        'entry_notes' => '',
        'entry_operation_uuid' => (string) Str::uuid(),
        ...$overrides,
    ];
}

it('lets the storekeeper register entries and consumptions', function () {
    $this->actingAs($this->storekeeper)
        ->post(route('warehouses.entries.store', $this->warehouse), datosDeEntrada($this->product))
        ->assertRedirect(route('inventory.index', ['almacen' => $this->warehouse->id]))
        ->assertSessionHas('status', 'Entrada de 50.000 registrada: quedan 50.000.');

    $this->actingAs($this->storekeeper)
        ->post(route('warehouses.exits.store', $this->warehouse), [
            'exit_movement_type' => InventoryMovementType::Consumption->value,
            'exit_product_id' => $this->product->id,
            'exit_quantity' => '12',
            'exit_notes' => 'Reposición de habitaciones',
            'exit_operation_uuid' => (string) Str::uuid(),
        ])
        ->assertSessionHas('status', 'Se registró: consumo interno de 12.000. Quedan 38.000.');

    $this->actingAs($this->storekeeper)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSeeInOrder(['Existencias en Depósito', 'Jabón de tocador', '38.000 pieza', 'Bs 2.4000', 'Bs 91.20'])
        ->assertSeeInOrder(['Últimos movimientos', 'Consumo interno', 'Reposición de habitaciones', 'Entrada', 'Factura 881'])
        ->assertDontSee('Ajustar por conteo físico');
});

it('shows the rejection of an exit larger than the stock on the form', function () {
    $this->actingAs($this->storekeeper)
        ->from(route('inventory.index'))
        ->post(route('warehouses.exits.store', $this->warehouse), [
            'exit_movement_type' => InventoryMovementType::Exit->value,
            'exit_product_id' => $this->product->id,
            'exit_quantity' => '1',
            'exit_notes' => 'Rotura',
            'exit_operation_uuid' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('inventory.index'))
        ->assertSessionHasErrors(['operation' => 'No hay existencias suficientes: quedan 0.000 (pieza).']);
});

it('rejects a sale or a transfer registered as a manual exit', function () {
    $this->actingAs($this->storekeeper)
        ->post(route('warehouses.exits.store', $this->warehouse), [
            'exit_movement_type' => InventoryMovementType::Sale->value,
            'exit_product_id' => $this->product->id,
            'exit_quantity' => '1',
            'exit_notes' => 'Venta',
            'exit_operation_uuid' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('exit_movement_type');
});

it('leaves adjustments to supervisors and admins', function () {
    InventoryBalance::factory()->for($this->warehouse)->for($this->product)->create(['quantity' => '10.000', 'average_cost' => '2.0000']);
    $adjustment = [
        'adjustment_product_id' => $this->product->id,
        'adjustment_counted_quantity' => '9',
        'adjustment_notes' => 'Conteo de fin de mes',
        'adjustment_operation_uuid' => (string) Str::uuid(),
    ];

    $this->actingAs($this->storekeeper)
        ->post(route('warehouses.adjustments.store', $this->warehouse), $adjustment)
        ->assertForbidden();

    $supervisor = usuarioDeInventario($this->branch, BranchRole::Supervisor);
    $this->actingAs($supervisor)->get(route('inventory.index'))->assertSee('Ajustar por conteo físico');
    $this->actingAs($supervisor)
        ->post(route('warehouses.adjustments.store', $this->warehouse), $adjustment)
        ->assertSessionHas('status', 'Ajuste registrado: quedan 9.000.');

    expect(InventoryMovement::query()->sole()->movement_type)->toBe(InventoryMovementType::Adjustment);
});

it('keeps the inventory away from receptionists', function () {
    $receptionist = usuarioDeInventario($this->branch, BranchRole::Receptionist);

    $this->actingAs($receptionist)->get(route('inventory.index'))->assertForbidden();
    $this->actingAs($receptionist)->post(route('warehouses.entries.store', $this->warehouse), datosDeEntrada($this->product))->assertForbidden();
    $this->actingAs($receptionist)->get(route('occupancy.index'))->assertDontSee(route('inventory.index'));
    $this->actingAs($this->storekeeper)->get(route('occupancy.index'))->assertSee(route('inventory.index'));
});

it('hides the warehouses of another branch', function () {
    $otherWarehouse = Warehouse::factory()->create(['name' => 'Almacén ajeno']);

    $this->actingAs($this->storekeeper)
        ->post(route('warehouses.entries.store', $otherWarehouse), datosDeEntrada($this->product))
        ->assertNotFound();

    $this->actingAs($this->storekeeper)
        ->get(route('inventory.index', ['almacen' => $otherWarehouse->id]))
        ->assertOk()
        ->assertSee('Existencias en Depósito')
        ->assertDontSee('Almacén ajeno');
});

it('switches between the active warehouses of the branch', function () {
    $minibar = Warehouse::factory()->for($this->branch)->create(['name' => 'Minibar']);
    Warehouse::factory()->for($this->branch)->inactivo()->create(['name' => 'Bodega cerrada']);

    $this->actingAs($this->storekeeper)
        ->get(route('inventory.index', ['almacen' => $minibar->id]))
        ->assertOk()
        ->assertSee('Existencias en Minibar')
        ->assertDontSee('Bodega cerrada');
});
