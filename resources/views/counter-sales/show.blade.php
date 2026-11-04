@php($isVoided = $counterSale->estaAnulada())
@php($hasCashPayment = $counterSale->payments->contains(fn ($payment) => $payment->payment_method->afectaEfectivo()))

<x-layouts.app title="Venta de mostrador #{{ $counterSale->id }}">
    <div class="flex gap-4 text-sm">
        <a href="{{ route('counter-sales.index') }}" class="text-gray-600 underline">Volver a ventas de mostrador</a>
    </div>

    <x-card>
        <dl class="grid gap-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-gray-500">Estado</dt>
                <dd>
                    @if ($isVoided)
                        <x-badge color="red">Anulada</x-badge>
                    @else
                        <x-badge color="green">Cobrada</x-badge>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Fecha</dt>
                <dd>{{ $counterSale->created_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Cliente</dt>
                <dd>{{ $counterSale->holder_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Vendió</dt>
                <dd>{{ $counterSale->createdByUser->name }}</dd>
            </div>
        </dl>
        @if ($isVoided)
            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                Anulada por {{ $counterSale->voidedByUser->name }} el {{ $counterSale->voided_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}: {{ $counterSale->void_reason }}
            </p>
        @endif
    </x-card>

    <x-card title="Productos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-ink-600">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Producto</th>
                        <th class="py-2 pr-4 text-right font-semibold">Cantidad</th>
                        <th class="py-2 pr-4 text-right font-semibold">Precio</th>
                        <th class="py-2 text-right font-semibold">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($counterSale->charges as $stayCharge)
                        <tr @class(['text-gray-400 line-through' => $stayCharge->status === \App\Enums\ChargeStatus::Voided])>
                            <td class="py-2 pr-4">{{ $stayCharge->description }}</td>
                            <td class="py-2 pr-4 text-right">{{ $stayCharge->quantity }}</td>
                            <td class="py-2 pr-4 text-right">Bs {{ $stayCharge->unit_price }}</td>
                            <td class="py-2 text-right">Bs {{ $stayCharge->total_amount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Pagos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-ink-600">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Método</th>
                        <th class="py-2 pr-4 font-semibold">Caja</th>
                        <th class="py-2 pr-4 text-right font-semibold">Importe</th>
                        <th class="py-2 pr-4 text-right font-semibold">Devuelto</th>
                        <th class="py-2 font-semibold">Recibo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($counterSale->payments as $payment)
                        @php($issuedReceipt = $payment->receipts->first())
                        <tr>
                            <td class="py-2 pr-4">{{ $payment->payment_method->etiqueta() }}{{ $payment->reference ? " · {$payment->reference}" : '' }}</td>
                            <td class="py-2 pr-4">{{ $payment->cashSession->cashRegister->name }}</td>
                            <td class="py-2 pr-4 text-right">Bs {{ $payment->amount }}</td>
                            <td class="py-2 pr-4 text-right">{{ $payment->refunded_amount ? "Bs {$payment->refunded_amount}" : '' }}</td>
                            <td class="py-2">
                                @if ($issuedReceipt)
                                    <a href="{{ route('receipts.show', $issuedReceipt) }}" class="underline">{{ $issuedReceipt->numeroCompleto() }}</a>
                                @elseif (! $isVoided)
                                    @can('emitir', [\App\Models\Receipt::class, $payment])
                                        <a href="{{ route('payments.receipts.create', $payment) }}" class="underline">Emitir recibo</a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    @unless ($isVoided)
        @can('anularVentaDeMostrador', $counterSale)
            <x-card title="Anular venta">
                <form method="POST" action="{{ route('counter-sales.void', $counterSale) }}" class="flex max-w-xl flex-col gap-3">
                    @csrf
                    <x-field label="Motivo" name="void_reason" required />
                    @if ($hasCashPayment)
                        <x-select label="Caja de la que sale el efectivo" name="void_cash_session_id"
                            :options="['' => 'Elegir caja'] + $openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all()" />
                    @endif
                    <p class="text-sm text-gray-600">Los productos vuelven al almacén y se devuelve el importe cobrado por el mismo método.</p>
                    <div>
                        <x-button variant="danger">Anular venta</x-button>
                    </div>
                </form>
            </x-card>
        @endcan
    @endunless
</x-layouts.app>
