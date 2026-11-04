<?php

use App\Enums\CheckoutExceptionStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CheckoutException;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\User;
use App\Services\CheckoutExceptionService;
use App\Services\FolioBalanceService;
use App\Services\ReceptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stay = Stay::factory()->for(Room::factory())->create();
    StayCharge::factory()->for($this->stay)->create(['total_amount' => '250.00']);
    $this->requester = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->operator = User::factory()->create();
});

function autorizarSalidaConDeuda(Stay $stay, User $requester, User $approver): CheckoutException
{
    $service = app(CheckoutExceptionService::class);

    return $service->aprobar($service->solicitar($stay, $requester, 'Paga mañana por transferencia'), $approver);
}

it('requests an exception for the current debt', function () {
    $checkoutException = app(CheckoutExceptionService::class)->solicitar($this->stay, $this->requester, 'Paga mañana');

    expect($checkoutException->status)->toBe(CheckoutExceptionStatus::Requested)
        ->and($checkoutException->outstanding_amount)->toBe('250.00')
        ->and($checkoutException->requested_by)->toBe($this->requester->id);
    $this->assertDatabaseHas('activity_log', ['event' => 'checkout_exception.requested', 'subject_id' => $checkoutException->id]);
});

it('does not request an exception for a stay without debt', function () {
    Payment::factory()->for($this->stay->folio)->create(['amount' => '250.00']);

    app(CheckoutExceptionService::class)->solicitar($this->stay, $this->requester, 'Paga mañana');
})->throws(BusinessRuleException::class, 'no tiene saldo pendiente');

it('approves for a limited time by someone else', function () {
    $this->travelTo(now()->startOfMinute());

    $checkoutException = autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);

    expect($checkoutException->status)->toBe(CheckoutExceptionStatus::Approved)
        ->and($checkoutException->approved_by)->toBe($this->approver->id)
        ->and($checkoutException->expires_at->equalTo(now()->addMinutes(config('lodging.checkout_exception_minutes'))))->toBeTrue()
        ->and($checkoutException->estaVigente())->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['event' => 'checkout_exception.approved', 'subject_id' => $checkoutException->id]);
});

it('does not let the requester approve their own request', function () {
    $service = app(CheckoutExceptionService::class);

    $service->aprobar($service->solicitar($this->stay, $this->requester, 'Paga mañana'), $this->requester);
})->throws(BusinessRuleException::class, 'Quien solicita la salida con deuda no puede autorizarla.');

it('does not approve a request whose debt changed', function () {
    $service = app(CheckoutExceptionService::class);
    $checkoutException = $service->solicitar($this->stay, $this->requester, 'Paga mañana');
    StayCharge::factory()->for($this->stay)->create(['total_amount' => '15.00', 'service_date' => now()->addDay()->toDateString()]);

    expect(fn () => $service->aprobar($checkoutException, $this->approver))
        ->toThrow(BusinessRuleException::class, 'Bs 250.00 solicitados, Bs 265.00 actuales');

    expect($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Requested);
});

it('replaces a pending request or a stale approval, but not a valid one', function () {
    $service = app(CheckoutExceptionService::class);
    $firstRequest = $service->solicitar($this->stay, $this->requester, 'Paga mañana');
    $secondRequest = $service->solicitar($this->stay, $this->requester, 'Paga el lunes');

    expect($firstRequest->refresh()->status)->toBe(CheckoutExceptionStatus::Revoked)
        ->and($firstRequest->resolved_by)->toBe($this->requester->id);

    $service->aprobar($secondRequest, $this->approver);
    expect(fn () => $service->solicitar($this->stay, $this->requester, 'Otra vez'))
        ->toThrow(BusinessRuleException::class, 'ya tiene una salida con deuda autorizada y vigente');

    $this->travel(config('lodging.checkout_exception_minutes') + 1)->minutes();
    $thirdRequest = $service->solicitar($this->stay, $this->requester, 'Paga el martes');

    expect($secondRequest->refresh()->status)->toBe(CheckoutExceptionStatus::Expired)
        ->and($thirdRequest->status)->toBe(CheckoutExceptionStatus::Requested);
});

it('rejects a request and revokes an approval', function () {
    $service = app(CheckoutExceptionService::class);
    $request = $service->solicitar($this->stay, $this->requester, 'Paga mañana');

    expect($service->rechazar($request, $this->approver, 'Sin garantía')->status)->toBe(CheckoutExceptionStatus::Rejected);

    $approval = autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);
    $revoked = $service->rechazar($approval, $this->approver, 'El huésped no dejó datos');

    expect($revoked->status)->toBe(CheckoutExceptionStatus::Revoked)
        ->and($revoked->resolution_notes)->toBe('El huésped no dejó datos');
    $this->assertDatabaseHas('activity_log', ['event' => 'checkout_exception.revoked', 'subject_id' => $approval->id]);

    expect(fn () => $service->rechazar($revoked, $this->approver, 'Otra vez'))->toThrow(BusinessRuleException::class, 'ya no está pendiente ni autorizada');
});

it('checks out with the authorized debt and consumes the exception', function () {
    $checkoutException = autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);

    $completedStay = app(ReceptionService::class)->registrarSalida($this->stay, $this->operator);

    expect($completedStay->status)->toBe(StayStatus::Completed)
        ->and((string) app(FolioBalanceService::class)->calcular($completedStay->folio))->toBe('250.00')
        ->and($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Used)
        ->and($checkoutException->used_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_log', ['event' => 'stay.checked_out', 'subject_id' => $this->stay->id, 'properties->checkout_exception_id' => $checkoutException->id]);
});

it('lets the requester record the check-out but not the approver', function () {
    autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);

    expect(fn () => app(ReceptionService::class)->registrarSalida($this->stay, $this->approver))
        ->toThrow(BusinessRuleException::class, 'Quien autorizó la salida con deuda no puede registrarla');

    expect(app(ReceptionService::class)->registrarSalida($this->stay, $this->requester)->status)->toBe(StayStatus::Completed);
});

it('does not check out with an expired approval', function () {
    autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);
    $this->travel(config('lodging.checkout_exception_minutes') + 1)->minutes();

    app(ReceptionService::class)->registrarSalida($this->stay, $this->operator);
})->throws(BusinessRuleException::class, 'venció');

it('does not check out when the debt changed after the approval', function () {
    $checkoutException = autorizarSalidaConDeuda($this->stay, $this->requester, $this->approver);
    StayCharge::factory()->for($this->stay)->create(['total_amount' => '15.00', 'service_date' => now()->addDay()->toDateString()]);

    expect(fn () => app(ReceptionService::class)->registrarSalida($this->stay, $this->operator))
        ->toThrow(BusinessRuleException::class, 'Bs 250.00 solicitados, Bs 265.00 actuales');

    expect($checkoutException->refresh()->status)->toBe(CheckoutExceptionStatus::Approved)
        ->and($this->stay->refresh()->status)->toBe(StayStatus::Active);
});

it('does not check out with a request that is still pending', function () {
    app(CheckoutExceptionService::class)->solicitar($this->stay, $this->requester, 'Paga mañana');

    app(ReceptionService::class)->registrarSalida($this->stay, $this->operator);
})->throws(BusinessRuleException::class, 'no hay una salida con deuda autorizada');

it('allows at most one open exception per stay in the database', function () {
    CheckoutException::factory()->for($this->stay)->create();

    CheckoutException::factory()->for($this->stay)->aprobada()->create();
})->throws(UniqueConstraintViolationException::class);
