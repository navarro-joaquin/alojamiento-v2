<?php

use App\Enums\CashCountType;
use App\Enums\CashSessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['cash_difference_threshold' => '10.00']);
    $this->cashSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->create(['opening_amount' => '200.00']);
    CashMovement::factory()->for($this->cashSession)->create(['amount' => '150.00']);
    $this->cashier = User::factory()->create();
    $this->supervisor = User::factory()->create();
});

it('records a partial count against the expected cash without closing the session', function () {
    $cashCount = app(CashService::class)->registrarArqueo($this->cashSession, $this->cashier, '345.00', notes: 'Cambio de turno');

    expect($cashCount->count_type)->toBe(CashCountType::Partial)
        ->and($cashCount->expected_amount)->toBe('350.00')
        ->and($cashCount->declared_amount)->toBe('345.00')
        ->and($cashCount->difference_amount)->toBe('-5.00')
        ->and($cashCount->counted_by)->toBe($this->cashier->id)
        ->and($this->cashSession->refresh()->status)->toBe(CashSessionStatus::Open);
    $this->assertDatabaseHas('activity_log', ['event' => 'cash_count.recorded', 'subject_id' => $cashCount->id]);
});

it('keeps the detail of bills and coins when it adds up to the amount', function () {
    $cashCount = app(CashService::class)->registrarArqueo($this->cashSession, $this->cashier, '350.00', [20000 => 1, 10000 => 1, 5000 => 1]);

    expect($cashCount->denominations)->toEqual(['20000' => 1, '10000' => 1, '5000' => 1]);

    expect(fn () => app(CashService::class)->registrarArqueo($this->cashSession, $this->cashier, '360.00', [20000 => 1, 10000 => 1, 5000 => 1]))
        ->toThrow(BusinessRuleException::class, 'El detalle de billetes y monedas suma Bs 350.00, no Bs 360.00.');
});

it('does not count a session that is no longer open', function () {
    $closedSession = CashSession::factory()->for(CashRegister::factory()->for($this->branch))->cerrada()->create();

    app(CashService::class)->registrarArqueo($closedSession, $this->cashier, '200.00');
})->throws(BusinessRuleException::class, 'no está abierta');

it('records the closing count with the session', function () {
    $closedSession = app(CashService::class)->cerrarSesion($this->cashSession, $this->cashier, '350.00', 'Todo en orden', [20000 => 1, 10000 => 1, 5000 => 1]);

    $closingCount = $closedSession->cashCounts()->sole();
    expect($closingCount->count_type)->toBe(CashCountType::Closing)
        ->and($closingCount->expected_amount)->toBe('350.00')
        ->and($closingCount->difference_amount)->toBe('0.00')
        ->and($closingCount->notes)->toBe('Todo en orden')
        ->and($closingCount->denominations)->toEqual(['20000' => 1, '10000' => 1, '5000' => 1]);
});

it('closes a pending review with the notes of another supervisor', function () {
    $closedSession = app(CashService::class)->cerrarSesion($this->cashSession, $this->cashier, '300.00');
    expect($closedSession->status)->toBe(CashSessionStatus::PendingReview);

    $reviewedSession = app(CashService::class)->revisarSesion($closedSession, $this->supervisor, 'Faltante descontado al cajero de turno');

    expect($reviewedSession->status)->toBe(CashSessionStatus::Closed)
        ->and($reviewedSession->reviewed_by)->toBe($this->supervisor->id)
        ->and($reviewedSession->reviewed_at)->not->toBeNull()
        ->and($reviewedSession->review_notes)->toBe('Faltante descontado al cajero de turno');
    $this->assertDatabaseHas('activity_log', ['event' => 'cash_session.reviewed', 'subject_id' => $closedSession->id]);
});

it('does not let whoever closed the session review it', function () {
    $closedSession = app(CashService::class)->cerrarSesion($this->cashSession, $this->cashier, '300.00');

    app(CashService::class)->revisarSesion($closedSession, $this->cashier, 'Revisado');
})->throws(BusinessRuleException::class, 'Quien cerró la caja no puede revisar su propio cierre.');

it('only reviews sessions pending review', function () {
    $closedSession = app(CashService::class)->cerrarSesion($this->cashSession, $this->cashier, '350.00');

    app(CashService::class)->revisarSesion($closedSession, $this->supervisor, 'Revisado');
})->throws(BusinessRuleException::class, 'no está pendiente de revisión');
