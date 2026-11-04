<?php

namespace App\Http\Controllers;

use App\Enums\FolioType;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CounterSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sales to people who are not staying, collected at once.
 */
class CounterSaleController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verVentasDeMostrador', [Folio::class, $branch]);

        return view('counter-sales.index', [
            'counterSales' => Folio::query()
                ->whereBelongsTo($branch)
                ->where('folio_type', FolioType::Counter)
                ->with(['payments', 'createdByUser'])
                ->withSum(['charges as total_amount' => fn ($query) => $query->confirmados()], 'total_amount')
                ->latest('id')
                ->limit(50)
                ->get(),
            'warehouses' => $branch->warehouses()->activos()->orderBy('name')->get(),
            'products' => Product::query()->activos()->vendibles()->orderBy('name')->get(),
            'openCashSessions' => CashSession::query()
                ->abiertas()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($branch))
                ->with('cashRegister')
                ->get(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Rows of the form without a product are ignored.
     */
    public function store(Request $request, CounterSaleService $counterSaleService): RedirectResponse
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('venderEnMostrador', [Folio::class, $branch]);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists(Warehouse::class, 'id')->where('branch_id', $branch->id)],
            'cash_session_id' => ['required', 'integer', Rule::exists(CashSession::class, 'id')],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer', 'distinct', Rule::exists(Product::class, 'id')],
            'items.*.quantity' => ['nullable', 'required_with:items.*.product_id', 'decimal:0,2', 'gt:0', 'max:9999'],
            'holder_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'warehouse_id' => 'almacén',
            'cash_session_id' => 'caja',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
            'holder_name' => 'cliente',
            'payment_method' => 'método',
            'reference' => 'referencia',
        ]);

        $quantitiesByProduct = collect($validated['items'])
            ->filter(fn (array $item): bool => filled($item['product_id'] ?? null))
            ->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (string) $item['quantity']])
            ->all();

        $counterSale = $counterSaleService->vender(
            Warehouse::findOrFail($validated['warehouse_id']),
            CashSession::findOrFail($validated['cash_session_id']),
            $request->user(),
            $quantitiesByProduct,
            PaymentMethod::from($validated['payment_method']),
            $validated['operation_uuid'],
            $validated['holder_name'] ?? null,
            $validated['reference'] ?? null,
        );

        return redirect()->route('counter-sales.show', $counterSale)->with('status', "Venta de mostrador #{$counterSale->id} cobrada.");
    }

    public function show(Request $request, Folio $counterSale): View
    {
        abort_unless($counterSale->folio_type === FolioType::Counter, 404);
        $this->asegurarDeLaSucursalActiva($request, $counterSale->branch_id);
        Gate::authorize('verVentasDeMostrador', [Folio::class, $this->sucursalActiva($request)]);

        $counterSale->load([
            'charges' => fn ($query) => $query->orderBy('id'),
            'payments' => fn ($query) => $query
                ->with(['cashSession.cashRegister', 'receipts' => fn ($query) => $query->where('status', ReceiptStatus::Issued)])
                ->withSum(['refunds as refunded_amount' => fn ($query) => $query->confirmadas()], 'amount')
                ->orderBy('id'),
            'createdByUser',
            'voidedByUser',
        ]);

        return view('counter-sales.show', [
            'counterSale' => $counterSale,
            'openCashSessions' => CashSession::query()
                ->abiertas()
                ->whereHas('cashRegister', fn ($query) => $query->whereBelongsTo($counterSale->branch))
                ->with('cashRegister')
                ->get(),
        ]);
    }
}
