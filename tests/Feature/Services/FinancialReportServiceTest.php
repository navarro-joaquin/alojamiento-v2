<?php

use App\Enums\ChargeDirection;
use App\Enums\ChargeType;
use App\Enums\CheckoutExceptionStatus;
use App\Enums\PaymentMethod;
use App\Enums\StayStatus;
use App\Models\Branch;
use App\Models\CheckoutException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Room;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Services\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['lodging.night_audit_hour' => 4]);
    $this->branch = Branch::factory()->create(['timezone' => 'America/La_Paz']);
    Room::factory()->for($this->branch)->create();
    $this->stay = Stay::factory()->for(Room::factory()->for($this->branch))->create();
    $this->businessDate = CarbonImmutable::parse('2026-11-03');

    StayCharge::factory()->for($this->stay)->create(['charge_type' => ChargeType::Lodging, 'total_amount' => '250.00', 'service_date' => '2026-11-03']);
    StayCharge::factory()->for($this->stay)->create([
        'charge_type' => ChargeType::Discount, 'direction' => ChargeDirection::Credit, 'total_amount' => '20.00', 'service_date' => '2026-11-03',
    ]);
    StayCharge::factory()->for($this->stay)->create(['charge_type' => ChargeType::Lodging, 'total_amount' => '250.00', 'service_date' => '2026-11-04']);

    $this->latePayment = Payment::factory()->for($this->stay)->create([
        'amount' => '200.00', 'paid_at' => CarbonImmutable::parse('2026-11-04 02:00', 'America/La_Paz')->utc(),
    ]);
    Payment::factory()->for($this->stay)->create([
        'amount' => '100.00', 'payment_method' => PaymentMethod::Card, 'paid_at' => CarbonImmutable::parse('2026-11-04 05:00', 'America/La_Paz')->utc(),
    ]);
    PaymentRefund::factory()->for($this->latePayment)->create(['amount' => '30.00', 'refunded_at' => CarbonImmutable::parse('2026-11-03 15:00', 'America/La_Paz')->utc()]);

    $category = ExpenseCategory::factory()->create(['name' => 'Limpieza']);
    Expense::factory()->for($this->branch)->for($category)->enEfectivo()->create(['amount' => '50.00', 'expense_date' => '2026-11-03']);
    Expense::factory()->for($this->branch)->for($category)->pendiente()->create(['expense_date' => '2026-11-03']);
});

it('adds up a business date, counting late-night payments in the previous one', function () {
    $report = app(FinancialReportService::class)->resumir($this->branch, $this->businessDate, $this->businessDate);

    expect($report['charges']->map(fn (array $row) => [$row['charge_type'], $row['total']])->all())
        ->toEqual([[ChargeType::Lodging, '250.00'], [ChargeType::Discount, '20.00']])
        ->and((string) $report['chargesNet'])->toBe('230.00')
        ->and($report['payments']->keys()->all())->toBe(['cash'])
        ->and($report['payments']['cash']['total'])->toBe('200.00')
        ->and($report['refunds']['cash']['total'])->toBe('30.00')
        ->and($report['expensesByCategory']->all())->toBe([['category' => 'Limpieza', 'count' => 1, 'total' => '50.00']])
        ->and((string) $report['netByMethod']['cash']['net'])->toBe('120.00')
        ->and($report['netByMethod']->has('card'))->toBeFalse()
        ->and($report['occupancy'])->toBe(['nightsSold' => 1, 'roomNights' => 2, 'rate' => '50.0'])
        ->and($report['paymentsWithoutInvoice']->pluck('id')->all())->toBe([$this->latePayment->id]);
});

it('covers every date of a range', function () {
    $report = app(FinancialReportService::class)->resumir($this->branch, $this->businessDate, $this->businessDate->addDay());

    expect($report['payments']['card']['total'])->toBe('100.00')
        ->and((string) $report['chargesNet'])->toBe('480.00')
        ->and($report['occupancy'])->toBe(['nightsSold' => 2, 'roomNights' => 4, 'rate' => '50.0']);
});

it('lists active stays that owe money and completed stays that left with debt', function () {
    $leftWithDebt = Stay::factory()->for(Room::factory()->for($this->branch))->finalizada()->create();
    StayCharge::factory()->for($leftWithDebt)->create(['total_amount' => '80.00']);
    CheckoutException::factory()->for($leftWithDebt)->create(['status' => CheckoutExceptionStatus::Used]);
    $paidOff = Stay::factory()->for(Room::factory()->for($this->branch))->create(['status' => StayStatus::Completed]);
    StayCharge::factory()->for($paidOff)->create(['total_amount' => '80.00']);
    Payment::factory()->for($paidOff)->create(['amount' => '80.00']);

    $outstanding = app(FinancialReportService::class)->listarSaldosPendientes($this->branch);

    expect($outstanding->map(fn (array $row) => [$row['stay']->id, (string) $row['balance']])->all())
        ->toBe([[$this->stay->id, '210.00'], [$leftWithDebt->id, '80.00']]);
});
