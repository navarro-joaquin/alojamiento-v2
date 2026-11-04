<?php

use App\Enums\BranchRole;
use App\Enums\ReceiptStatus;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['code' => 'CEN', 'name' => 'Sucursal Centro']);
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch)->state(['room_number' => '204']))->create();
    $this->stay->guests()->attach(
        Guest::factory()->create(['first_name' => 'Ana', 'last_name' => 'Quispe', 'document_number' => '1234567', 'document_complement' => '1A']),
        ['is_primary' => true],
    );
    $this->payment = Payment::factory()->for($this->stay->folio)->create(['amount' => '250.00']);
    $this->receptionist = personalDeRecibos($this->branch, BranchRole::Receptionist);
    $this->supervisor = personalDeRecibos($this->branch, BranchRole::Supervisor);
});

function personalDeRecibos(Branch $branch, BranchRole $role): User
{
    $user = User::factory()->create();
    $user->asignarRolEnSucursal($role, $branch->id);

    return $user;
}

it('issues a receipt from the stay page with the primary guest as holder', function () {
    $this->actingAs($this->receptionist)
        ->get(route('stays.show', $this->stay))
        ->assertSee(route('payments.receipts.create', $this->payment));

    $this->actingAs($this->receptionist)
        ->get(route('payments.receipts.create', $this->payment))
        ->assertOk()
        ->assertSee('value="Ana Quispe"', false)
        ->assertSee('value="1234567-1A"', false);

    $response = $this->actingAs($this->receptionist)
        ->post(route('payments.receipts.store', $this->payment), ['holder_name' => 'Ana Quispe', 'holder_document' => '1234567-1A']);

    $receipt = Receipt::query()->sole();
    $response->assertRedirect(route('receipts.show', $receipt))->assertSessionHas('status', 'Recibo CEN-000001 emitido.');

    $this->actingAs($this->receptionist)
        ->get(route('receipts.show', $receipt))
        ->assertOk()
        ->assertSee('Recibo N.º CEN-000001')
        ->assertSee('Sucursal Centro')
        ->assertSee('Bs 250.00')
        ->assertSee('habitación 204')
        ->assertDontSee('Anular recibo');

    $this->actingAs($this->receptionist)
        ->get(route('stays.show', $this->stay))
        ->assertSee('CEN-000001')
        ->assertDontSee(route('payments.receipts.create', $this->payment));
});

it('shows a second receipt for the same payment as a form error', function () {
    Receipt::factory()->for($this->payment)->create(['prefix' => 'CEN', 'number' => 1]);

    $this->actingAs($this->receptionist)
        ->from(route('payments.receipts.create', $this->payment))
        ->post(route('payments.receipts.store', $this->payment), ['holder_name' => 'Ana Quispe'])
        ->assertSessionHasErrors(['operation' => 'El pago ya tiene el recibo CEN-000001: anúlelo antes de emitir otro.']);
});

it('lets only a supervisor void a receipt', function () {
    $receipt = Receipt::factory()->for($this->payment)->create();

    $this->actingAs($this->receptionist)
        ->post(route('receipts.void', $receipt), ['void_reason' => 'Error'])
        ->assertForbidden();

    $this->actingAs($this->supervisor)
        ->post(route('receipts.void', $receipt), ['void_reason' => ''])
        ->assertSessionHasErrors('void_reason');

    $this->actingAs($this->supervisor)
        ->post(route('receipts.void', $receipt), ['void_reason' => 'Titular equivocado'])
        ->assertRedirect(route('receipts.show', $receipt));

    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Voided);

    $this->actingAs($this->supervisor)
        ->get(route('receipts.show', $receipt))
        ->assertSee('Titular equivocado')
        ->assertDontSee('Imprimir');
});

it('records the external invoice number from the stay page', function () {
    $this->actingAs($this->receptionist)
        ->put(route('payments.external-invoice.update', $this->payment), ['external_invoice_number' => '000123'])
        ->assertRedirect(route('stays.show', $this->stay))
        ->assertSessionHas('status', 'Número de factura guardado.');

    expect($this->payment->refresh()->external_invoice_number)->toBe('000123');
});

it('keeps receipts away from other branches and housekeeping', function () {
    $otherReceipt = Receipt::factory()->create();
    $housekeeper = personalDeRecibos($this->branch, BranchRole::Housekeeping);

    $this->actingAs($this->supervisor)->get(route('receipts.show', $otherReceipt))->assertNotFound();
    $this->actingAs($this->supervisor)->post(route('payments.receipts.store', $otherReceipt->payment), ['holder_name' => 'X'])->assertNotFound();
    $this->actingAs($housekeeper)->post(route('payments.receipts.store', $this->payment), ['holder_name' => 'X'])->assertForbidden();
    $this->actingAs($housekeeper)->put(route('payments.external-invoice.update', $this->payment), ['external_invoice_number' => '1'])->assertForbidden();

    expect(Receipt::count())->toBe(1);
});
