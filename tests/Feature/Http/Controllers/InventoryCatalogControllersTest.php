<?php

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->superAdmin = User::factory()->superAdministrador()->create();
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create();
    $this->admin->asignarRolEnSucursal(BranchRole::Admin, $this->branch->id);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeProducto(array $overrides = []): array
{
    return [
        'product_category_id' => '',
        'sku' => ' gas-500 ',
        'name' => 'Agua sin gas 500 ml',
        'unit' => 'botella',
        'sale_price' => '8.50',
        'is_sellable' => '1',
        'is_active' => '1',
        ...$overrides,
    ];
}

it('creates and updates a product category', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('product-categories.store'), ['name' => 'Bebidas', 'description' => 'Minibar y recepción'])
        ->assertRedirect(route('product-categories.index'));

    $productCategory = ProductCategory::query()->sole();

    $this->actingAs($this->superAdmin)
        ->put(route('product-categories.update', $productCategory), ['name' => 'Bebidas frías', 'description' => ''])
        ->assertRedirect(route('product-categories.index'));

    expect($productCategory->fresh()->only(['name', 'description']))->toBe(['name' => 'Bebidas frías', 'description' => null]);
});

it('creates a sellable product with an upper case code', function () {
    $productCategory = ProductCategory::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post(route('products.store'), datosDeProducto(['product_category_id' => $productCategory->id]))
        ->assertRedirect(route('products.index'));

    $product = Product::query()->sole();
    expect($product->sku)->toBe('GAS-500')
        ->and($product->sale_price)->toBe('8.50')
        ->and($product->is_sellable)->toBeTrue()
        ->and($product->category->is($productCategory))->toBeTrue();

    $this->actingAs($this->superAdmin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSeeInOrder(['GAS-500', 'Agua sin gas 500 ml', $productCategory->name, 'Bs 8.50']);
});

it('requires a sale price only for products sold to guests', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('products.store'), datosDeProducto(['sale_price' => '']))
        ->assertSessionHasErrors('sale_price');

    $this->actingAs($this->superAdmin)
        ->post(route('products.store'), datosDeProducto(['sale_price' => '', 'is_sellable' => '0']))
        ->assertRedirect(route('products.index'));

    expect(Product::query()->sole()->is_sellable)->toBeFalse();
});

it('rejects a duplicated product code', function () {
    $product = Product::factory()->create(['sku' => 'GAS-500']);

    $this->actingAs($this->superAdmin)
        ->post(route('products.store'), datosDeProducto())
        ->assertSessionHasErrors('sku');

    $this->actingAs($this->superAdmin)
        ->put(route('products.update', $product), datosDeProducto(['name' => 'Agua 500 ml']))
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->name)->toBe('Agua 500 ml');
});

it('keeps the product catalog away from branch admins', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)->get(route('products.index'))->assertForbidden();
    $this->actingAs($this->admin)->post(route('products.store'), datosDeProducto())->assertForbidden();
    $this->actingAs($this->admin)->put(route('products.update', $product), datosDeProducto())->assertForbidden();
    $this->actingAs($this->admin)->post(route('product-categories.store'), ['name' => 'Bebidas'])->assertForbidden();
});

it('lets the branch admin create and update warehouses of the active branch', function () {
    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), ['name' => 'Minibar', 'is_active' => '1'])
        ->assertRedirect(route('warehouses.index'));

    $warehouse = Warehouse::query()->sole();
    expect($warehouse->branch_id)->toBe($this->branch->id);

    $this->actingAs($this->admin)
        ->put(route('warehouses.update', $warehouse), ['name' => 'Minibar', 'is_active' => '0'])
        ->assertRedirect(route('warehouses.index'));

    expect($warehouse->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('warehouses.index'))
        ->assertOk()
        ->assertSee('Minibar')
        ->assertSee('Inactivo');
});

it('allows the same warehouse name in different branches only', function () {
    Warehouse::factory()->for($this->branch)->create(['name' => 'Depósito']);
    Warehouse::factory()->create(['name' => 'Recepción']);

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), ['name' => 'Depósito'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->admin)
        ->post(route('warehouses.store'), ['name' => 'Recepción'])
        ->assertRedirect(route('warehouses.index'));
});

it('hides the warehouses of another branch', function () {
    $otherWarehouse = Warehouse::factory()->create();

    $this->actingAs($this->admin)->get(route('warehouses.edit', $otherWarehouse))->assertNotFound();
    $this->actingAs($this->admin)->put(route('warehouses.update', $otherWarehouse), ['name' => 'Mío'])->assertNotFound();
});

it('keeps warehouses away from a supervisor', function () {
    $supervisor = User::factory()->create();
    $supervisor->asignarRolEnSucursal(BranchRole::Supervisor, $this->branch->id);

    $this->actingAs($supervisor)->get(route('warehouses.index'))->assertForbidden();
    $this->actingAs($supervisor)->post(route('warehouses.store'), ['name' => 'Minibar'])->assertForbidden();
    $this->actingAs($this->admin)->get(route('occupancy.index'))->assertSee(route('warehouses.index'));
    $this->actingAs($supervisor)->get(route('occupancy.index'))->assertDontSee(route('warehouses.index'));
});
