<?php

namespace App\Services;

use App\Enums\ChargeDirection;
use App\Enums\ChargeType;
use App\Enums\CheckoutExceptionStatus;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Enums\StayStatus;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Stay;
use App\Models\StayCharge;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Financial figures of a branch over a range of business dates.
 *
 * Charges and expenses carry their business date. Payments and refunds carry
 * a timestamp, so a business date covers from the night audit hour of that
 * day to the same hour of the next one (branch local time): a payment at
 * 01:00 belongs to the previous business date, as the night it pays for.
 */
class FinancialReportService
{
    public function __construct(
        private StayBalanceService $stayBalanceService,
    ) {}

    /**
     * @return array{
     *     charges: Collection<int, array{charge_type: ChargeType, direction: ChargeDirection, total: string}>,
     *     chargesNet: Number,
     *     payments: Collection<string, array{method: PaymentMethod, count: int, total: string}>,
     *     refunds: Collection<string, array{method: PaymentMethod, count: int, total: string}>,
     *     expensesByCategory: Collection<int, array{category: string, count: int, total: string}>,
     *     netByMethod: Collection<string, array{method: PaymentMethod, payments: Number, refunds: Number, expenses: Number, net: Number}>,
     *     cashSessions: Collection<int, CashSession>,
     *     occupancy: array{nightsSold: int, roomNights: int, rate: string},
     *     paymentsWithoutInvoice: Collection<int, Payment>,
     * }
     */
    public function resumir(Branch $branch, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        [$windowStart, $windowEnd] = $this->ventanaDeFechasDeNegocio($branch, $fromDate, $toDate);

        $charges = StayCharge::query()
            ->confirmados()
            ->whereHas('stay', fn ($query) => $query->whereBelongsTo($branch))
            ->whereBetween('service_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw('charge_type, direction, SUM(total_amount) as total')
            ->groupBy('charge_type', 'direction')
            ->orderByDesc('direction')
            ->orderBy('charge_type')
            ->get()
            ->map(fn (StayCharge $row): array => [
                'charge_type' => $row->charge_type,
                'direction' => $row->direction,
                'total' => (string) (new Number((string) $row->total))->round(2),
            ]);

        $payments = Payment::query()
            ->confirmados()
            ->whereHas('stay', fn ($query) => $query->whereBelongsTo($branch))
            ->where('paid_at', '>=', $windowStart)
            ->where('paid_at', '<', $windowEnd)
            ->selectRaw('payment_method, COUNT(*) as operations, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn (Payment $row): array => [$row->payment_method->value => [
                'method' => $row->payment_method,
                'count' => (int) $row->operations,
                'total' => (string) (new Number((string) $row->total))->round(2),
            ]]);

        $refunds = PaymentRefund::query()
            ->confirmadas()
            ->whereHas('payment.stay', fn ($query) => $query->whereBelongsTo($branch))
            ->where('refunded_at', '>=', $windowStart)
            ->where('refunded_at', '<', $windowEnd)
            ->selectRaw('refund_method, COUNT(*) as operations, SUM(amount) as total')
            ->groupBy('refund_method')
            ->get()
            ->mapWithKeys(fn (PaymentRefund $row): array => [$row->refund_method->value => [
                'method' => $row->refund_method,
                'count' => (int) $row->operations,
                'total' => (string) (new Number((string) $row->total))->round(2),
            ]]);

        $expenses = Expense::query()
            ->whereBelongsTo($branch)
            ->where('status', ExpenseStatus::Confirmed)
            ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->with('expenseCategory')
            ->get();

        return [
            'charges' => $charges,
            'chargesNet' => $charges->reduce(
                fn (Number $net, array $row): Number => $row['direction'] === ChargeDirection::Debit ? $net + new Number($row['total']) : $net - new Number($row['total']),
                new Number('0.00'),
            ),
            'payments' => $payments,
            'refunds' => $refunds,
            'expensesByCategory' => $expenses
                ->groupBy(fn (Expense $expense): string => $expense->expenseCategory->name)
                ->map(fn (Collection $categoryExpenses, string $category): array => [
                    'category' => $category,
                    'count' => $categoryExpenses->count(),
                    'total' => (string) $this->sumar($categoryExpenses->pluck('amount')),
                ])
                ->sortKeys()
                ->values(),
            'netByMethod' => $this->calcularNetoPorMetodo($payments, $refunds, $expenses),
            'cashSessions' => CashSession::query()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($branch))
                ->whereBetween('business_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->with(['cashRegister', 'openedByUser', 'closedByUser'])
                ->orderBy('business_date')
                ->orderBy('opened_at')
                ->get(),
            'occupancy' => $this->calcularOcupacion($branch, $fromDate, $toDate),
            'paymentsWithoutInvoice' => Payment::query()
                ->confirmados()
                ->whereHas('stay', fn ($query) => $query->whereBelongsTo($branch))
                ->where('paid_at', '>=', $windowStart)
                ->where('paid_at', '<', $windowEnd)
                ->whereNull('external_invoice_number')
                ->with('stay.room')
                ->orderBy('paid_at')
                ->limit(200)
                ->get(),
        ];
    }

    /**
     * Active stays that owe money and completed stays that left with debt,
     * whatever their dates.
     *
     * @return Collection<int, array{stay: Stay, balance: Number}>
     */
    public function listarSaldosPendientes(Branch $branch): Collection
    {
        return Stay::query()
            ->whereBelongsTo($branch)
            ->where(fn ($query) => $query
                ->where('status', StayStatus::Active)
                ->orWhere(fn ($query) => $query
                    ->where('status', StayStatus::Completed)
                    ->whereHas('checkoutExceptions', fn ($query) => $query->where('status', CheckoutExceptionStatus::Used))))
            ->with(['room', 'guests'])
            ->get()
            ->map(fn (Stay $stay): array => ['stay' => $stay, 'balance' => $this->stayBalanceService->calcular($stay)])
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->sort(fn (array $first, array $second): int => $second['balance'] <=> $first['balance'])
            ->values();
    }

    /**
     * UTC bounds of the business dates: from the night audit hour of the
     * first date to that hour of the day after the last one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function ventanaDeFechasDeNegocio(Branch $branch, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $cutOffHour = (int) config('lodging.night_audit_hour');

        return [
            CarbonImmutable::parse($fromDate->toDateString(), $branch->timezone)->setTime($cutOffHour, 0)->utc(),
            CarbonImmutable::parse($toDate->toDateString(), $branch->timezone)->addDay()->setTime($cutOffHour, 0)->utc(),
        ];
    }

    /**
     * @param  Collection<string, array{method: PaymentMethod, count: int, total: string}>  $payments
     * @param  Collection<string, array{method: PaymentMethod, count: int, total: string}>  $refunds
     * @param  Collection<int, Expense>  $expenses
     * @return Collection<string, array{method: PaymentMethod, payments: Number, refunds: Number, expenses: Number, net: Number}>
     */
    private function calcularNetoPorMetodo(Collection $payments, Collection $refunds, Collection $expenses): Collection
    {
        return collect(PaymentMethod::cases())
            ->mapWithKeys(function (PaymentMethod $method) use ($payments, $refunds, $expenses): array {
                $collected = new Number($payments[$method->value]['total'] ?? '0.00');
                $refunded = new Number($refunds[$method->value]['total'] ?? '0.00');
                $spent = $this->sumar($expenses->where('payment_method', $method)->pluck('amount'));

                return [$method->value => [
                    'method' => $method,
                    'payments' => $collected,
                    'refunds' => $refunded,
                    'expenses' => $spent,
                    'net' => ($collected - $refunded - $spent)->round(2),
                ]];
            })
            ->filter(fn (array $row): bool => $row['payments'] != 0 || $row['refunds'] != 0 || $row['expenses'] != 0);
    }

    /**
     * Lodging nights charged over the rooms of the branch times the days.
     *
     * @return array{nightsSold: int, roomNights: int, rate: string}
     */
    private function calcularOcupacion(Branch $branch, CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $nightsSold = StayCharge::query()
            ->confirmados()
            ->where('charge_type', ChargeType::Lodging)
            ->whereHas('stay', fn ($query) => $query->whereBelongsTo($branch))
            ->whereBetween('service_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->count();

        $roomNights = $branch->rooms()->count() * ((int) $fromDate->diffInDays($toDate) + 1);

        return [
            'nightsSold' => $nightsSold,
            'roomNights' => $roomNights,
            'rate' => $roomNights > 0 ? number_format($nightsSold * 100 / $roomNights, 1) : '0.0',
        ];
    }

    /**
     * @param  Collection<int, string>  $amounts
     */
    private function sumar(Collection $amounts): Number
    {
        return $amounts->reduce(fn (Number $total, string $amount): Number => $total + new Number($amount), new Number('0.00'))->round(2);
    }
}
