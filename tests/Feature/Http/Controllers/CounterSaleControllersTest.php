<?php

use App\Enums\BranchRole;
use App\Enums\FolioType;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Room;
use App\Models\Stay;
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
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch)->state(['name' => 'Caja recepción']))->create();
    $this->store = Warehouse::factory()->for($this->branch)->create(['name' => 'Tienda']);
    $this->soda = Product::factory()->vendible('12.50')->create(['name' => 'Gaseosa 500 ml']);
    InventoryBalance::factory()->for($this->store)->for($this->soda)->create(['quantity' => '10.000']);
    $this->receptionist = usuarioDeMostrador($this->branch, BranchRole::Receptionist);
});

function usuarioDeMostrador(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDeVentaDeMostrador(array $overrides = []): array
{
    $test = test();

    return [
        'warehouse_id' => $test->store->id,
        'cash_session_id' => $test->cashSession->id,
        'items' => [
            ['product_id' => $test->soda->id, 'quantity' => '2'],
            ['product_id' => '', 'quantity' => ''],
        ],
        'holder_name' => 'Juan Pérez',
        'payment_method' => 'cash',
        'operation_uuid' => (string) Str::uuid(),
        ...$overrides,
    ];
}

it('sells at the counter and shows the sale', function () {
    $this->actingAs($this->receptionist)
        ->post(route('counter-sales.store'), datosDeVentaDeMostrador())
        ->assertSessionHasNoErrors();

    $counterSale = Folio::query()->where('folio_type', FolioType::Counter)->sole();

    $this->actingAs($this->receptionist)
        ->get(route('counter-sales.show', $counterSale))
        ->assertOk()
        ->assertSeeInOrder(['Juan Pérez', 'Gaseosa 500 ml', 'Bs 25.00', 'Efectivo', 'Caja recepción', 'Bs 25.00', 'Emitir recibo'])
        ->assertDontSee('Anular venta');

    $this->actingAs($this->receptionist)
        ->get(route('counter-sales.index'))
        ->assertOk()
        ->assertSeeInOrder(["#{$counterSale->id}", 'Juan Pérez', 'Efectivo', 'Bs 25.00', 'Cobrada']);
});

it('issues a receipt of a counter sale to its client', function () {
    $this->actingAs($this->receptionist)->post(route('counter-sales.store'), datosDeVentaDeMostrador());
    $payment = Folio::query()->where('folio_type', FolioType::Counter)->sole()->payments()->sole();

    $this->actingAs($this->receptionist)
        ->get(route('payments.receipts.create', $payment))
        ->assertOk()
        ->assertSee('Venta de mostrador')
        ->assertSee('value="Juan Pérez"', false);

    $this->actingAs($this->receptionist)
        ->post(route('payments.receipts.store', $payment), ['holder_name' => 'Juan Pérez'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->receptionist)
        ->get(route('receipts.show', $payment->receipts()->sole()))
        ->assertOk()
        ->assertSee("Venta de mostrador #{$payment->folio_id}")
        ->assertSee(route('counter-sales.show', $payment->folio_id));
});

it('shows a stock shortage as a form error', function () {
    $this->actingAs($this->receptionist)
        ->from(route('counter-sales.index'))
        ->post(route('counter-sales.store'), datosDeVentaDeMostrador(['items' => [['product_id' => $this->soda->id, 'quantity' => '11']]]))
        ->assertRedirect(route('counter-sales.index'))
        ->assertSessionHasErrors('operation');

    expect(Folio::query()->where('folio_type', FolioType::Counter)->exists())->toBeFalse();
});

it('rejects a warehouse of another branch', function () {
    $otherWarehouse = Warehouse::factory()->create();

    $this->actingAs($this->receptionist)
        ->post(route('counter-sales.store'), datosDeVentaDeMostrador(['warehouse_id' => $otherWarehouse->id]))
        ->assertSessionHasErrors('warehouse_id');
});

it('leaves voiding a sale to supervisors and admins', function () {
    $this->actingAs($this->receptionist)->post(route('counter-sales.store'), datosDeVentaDeMostrador());
    $counterSale = Folio::query()->where('folio_type', FolioType::Counter)->sole();

    $this->actingAs($this->receptionist)
        ->post(route('counter-sales.void', $counterSale), ['void_reason' => 'Error', 'void_cash_session_id' => $this->cashSession->id])
        ->assertForbidden();

    $supervisor = usuarioDeMostrador($this->branch, BranchRole::Supervisor);
    $this->actingAs($supervisor)
        ->get(route('counter-sales.show', $counterSale))
        ->assertSee('Anular venta');

    $this->actingAs($supervisor)
        ->post(route('counter-sales.void', $counterSale), ['void_reason' => 'Cliente se arrepintió', 'void_cash_session_id' => $this->cashSession->id])
        ->assertRedirect(route('counter-sales.show', $counterSale))
        ->assertSessionHas('status', "Venta de mostrador #{$counterSale->id} anulada.");

    $this->actingAs($supervisor)
        ->get(route('counter-sales.show', $counterSale))
        ->assertSeeInOrder(['Anulada', 'Cliente se arrepintió'])
        ->assertDontSee('Anular venta')
        ->assertDontSee('Emitir recibo');
});

it('hides the counter sales of another branch', function () {
    $otherSale = Folio::factory()->create();

    $this->actingAs($this->receptionist)->get(route('counter-sales.show', $otherSale))->assertNotFound();
});

it('does not show stay folios as counter sales', function () {
    $stayFolio = Stay::factory()->for(Room::factory()->for($this->branch))->create()->folio;

    $this->actingAs($this->receptionist)->get(route('counter-sales.show', $stayFolio))->assertNotFound();
});

it('keeps counter sales away from housekeeping', function () {
    $housekeeper = usuarioDeMostrador($this->branch, BranchRole::Housekeeping);

    $this->actingAs($housekeeper)->get(route('counter-sales.index'))->assertForbidden();
    $this->actingAs($housekeeper)->post(route('counter-sales.store'), datosDeVentaDeMostrador())->assertForbidden();
    $this->actingAs($housekeeper)->get(route('occupancy.index'))->assertDontSee(route('counter-sales.index'));
    $this->actingAs($this->receptionist)->get(route('occupancy.index'))->assertSee(route('counter-sales.index'));
});
