<?php

use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Room;
use App\Models\Stay;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['code' => 'CEN']);
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch))->create();
    $this->payment = Payment::factory()->for($this->stay)->create(['amount' => '250.00']);
    $this->clerk = User::factory()->create();
});

it('numbers the receipts of a branch correlatively with its code', function () {
    $service = app(ReceiptService::class);

    $firstReceipt = $service->emitir($this->payment, $this->clerk, 'Ana Quispe', '1234567-1A');
    $secondReceipt = $service->emitir(Payment::factory()->for($this->stay)->create(), $this->clerk, 'Ana Quispe');
    $otherBranchReceipt = $service->emitir(Payment::factory()->create(), $this->clerk, 'Luis Mamani');

    expect($firstReceipt->numeroCompleto())->toBe('CEN-000001')
        ->and($secondReceipt->numeroCompleto())->toBe('CEN-000002')
        ->and($otherBranchReceipt->number)->toBe(1)
        ->and($firstReceipt->amount)->toBe('250.00')
        ->and($firstReceipt->holder_document)->toBe('1234567-1A')
        ->and($firstReceipt->status)->toBe(ReceiptStatus::Issued);
    $this->assertDatabaseHas('activity_log', ['event' => 'receipt.issued', 'subject_id' => $firstReceipt->id]);
});

it('stores the holder document encrypted', function () {
    $receipt = app(ReceiptService::class)->emitir($this->payment, $this->clerk, 'Ana Quispe', '1234567');

    expect(DB::table('receipts')->where('id', $receipt->id)->value('holder_document'))->not->toBe('1234567');
});

it('does not issue a second receipt for a payment until the first is voided', function () {
    $service = app(ReceiptService::class);
    $firstReceipt = $service->emitir($this->payment, $this->clerk, 'Ana Quispe');

    expect(fn () => $service->emitir($this->payment, $this->clerk, 'Ana Quispe'))
        ->toThrow(BusinessRuleException::class, 'El pago ya tiene el recibo CEN-000001: anúlelo antes de emitir otro.');

    $voided = $service->anular($firstReceipt, $this->clerk, 'Titular equivocado');
    $replacement = $service->emitir($this->payment, $this->clerk, 'Hotelera SRL', '1020304050');

    expect($voided->status)->toBe(ReceiptStatus::Voided)
        ->and($voided->void_reason)->toBe('Titular equivocado')
        ->and($replacement->numeroCompleto())->toBe('CEN-000002');
    $this->assertDatabaseHas('activity_log', ['event' => 'receipt.voided', 'subject_id' => $firstReceipt->id]);

    expect(fn () => $service->anular($voided, $this->clerk, 'Otra vez'))->toThrow(BusinessRuleException::class, 'El recibo ya está anulado.');
});

it('does not issue a receipt for a payment that is not confirmed', function () {
    $this->payment->update(['status' => PaymentStatus::Voided]);

    app(ReceiptService::class)->emitir($this->payment, $this->clerk, 'Ana Quispe');
})->throws(BusinessRuleException::class, 'Solo se emiten recibos de pagos confirmados.');

it('records the external invoice number of a payment', function () {
    $payment = app(ReceiptService::class)->registrarFacturaExterna($this->payment, $this->clerk, '000123');

    expect($payment->external_invoice_number)->toBe('000123');
    $this->assertDatabaseHas('activity_log', ['event' => 'payment.external_invoice_recorded', 'subject_id' => $this->payment->id]);
});

it('allows at most one receipt in force per payment in the database', function () {
    Receipt::factory()->for($this->payment)->create();

    Receipt::factory()->for($this->payment)->create();
})->throws(QueryException::class, 'Duplicate entry');
