<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ReceiptStatus;
use App\Http\Requests\StoreStayRequest;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Room;
use App\Models\Stay;
use App\Services\ChargeService;
use App\Services\GuestService;
use App\Services\ReceptionService;
use App\Services\StayBalanceService;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StayController extends Controller
{
    public function create(Room $room): View
    {
        Gate::authorize('registrarIngreso', $room);

        return view('stays.create', [
            'room' => $room->load(['roomType', 'branch']),
            'documentTypes' => DocumentType::cases(),
        ]);
    }

    public function store(StoreStayRequest $request, Room $room, GuestService $guestService, ReceptionService $receptionService): RedirectResponse
    {
        $branch = $room->branch;
        $expectedCheckOutDate = $request->validated('expected_check_out_date');

        $stay = $receptionService->registrarIngreso(
            $room,
            $request->user(),
            $guestService->identificar($request->huespedPrincipal()),
            array_map($guestService->identificar(...), $request->acompanantes()),
            (int) $request->validated('adults'),
            (int) $request->validated('children'),
            $expectedCheckOutDate === null
                ? null
                : CarbonImmutable::parse("{$expectedCheckOutDate} {$branch->check_out_time}", $branch->timezone)->utc(),
            $request->validated('notes'),
        );

        return redirect()->route('stays.show', $stay)->with('status', "Ingreso registrado en la habitación {$room->room_number}.");
    }

    public function show(Request $request, Stay $stay, StayBalanceService $stayBalanceService): View
    {
        $this->asegurarDeLaSucursalActiva($request, $stay->branch_id);

        $stay->load([
            'room.roomType',
            'guests',
            'charges' => fn ($query) => $query->with('createdByUser')->orderBy('service_date')->orderBy('id'),
            'payments' => fn ($query) => $query->with(['cashSession.cashRegister', 'createdByUser', 'receipts' => fn ($query) => $query->where('status', ReceiptStatus::Issued)])
                ->withSum(['refunds as refunded_amount' => fn ($query) => $query->confirmadas()], 'amount')
                ->orderBy('paid_at'),
        ]);

        $balance = $stayBalanceService->calcular($stay);
        $openCheckoutException = $stay->checkoutExceptions()->abiertas()->with(['requestedByUser', 'approvedByUser'])->first();

        return view('stays.show', [
            'stay' => $stay,
            'balance' => $balance,
            'openCheckoutException' => $openCheckoutException,
            'checkoutExceptionMatchesDebt' => $openCheckoutException !== null && new Number($openCheckoutException->outstanding_amount) == $balance,
            'openCashSessions' => CashSession::query()
                ->abiertas()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($stay->branch))
                ->with('cashRegister')
                ->get(),
            'refundablePayments' => $stay->payments
                ->filter(fn (Payment $payment): bool => $payment->status === PaymentStatus::Confirmed)
                ->each(fn (Payment $payment) => $payment->refundable_amount = (string) (new Number($payment->amount) - new Number((string) ($payment->refunded_amount ?? '0'))))
                ->filter(fn (Payment $payment): bool => new Number($payment->refundable_amount) > 0),
            'manualChargeTypes' => ChargeService::MANUAL_DEBIT_TYPES,
            'saleWarehouses' => $stay->branch->warehouses()->activos()->orderBy('name')->get(),
            'sellableProducts' => Product::query()->vendibles()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'paymentTypes' => PaymentType::cases(),
        ]);
    }
}
