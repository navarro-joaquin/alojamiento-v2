@php
    $isActive = $stay->status === \App\Enums\StayStatus::Active;
    $primaryGuest = $stay->guests->firstWhere('pivot.is_primary', true);
@endphp

<x-layouts.app title="Estadía · habitación {{ $stay->room->room_number }}">
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Resumen" class="lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Estado</dt>
                    <dd><x-badge :color="$isActive ? 'blue' : 'gray'">{{ $stay->status->etiqueta() }}</x-badge></dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Ingreso</dt>
                    <dd>{{ $stay->check_in_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">{{ $isActive ? 'Salida prevista' : 'Salida' }}</dt>
                    <dd>{{ ($isActive ? $stay->expected_check_out_at : $stay->check_out_at)?->timezone($activeBranch->timezone)->format('d/m/Y H:i') ?? 'Sin fecha' }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Tarifa por noche</dt>
                    <dd>Bs {{ $stay->rate_amount }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Huéspedes</dt>
                    <dd>{{ $stay->adults }} adultos · {{ $stay->children }} niños</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Habitación</dt>
                    <dd>{{ $stay->room->room_number }} · {{ $stay->room->roomType->name }}</dd>
                </div>
            </dl>

            <ul class="flex flex-col gap-1 text-sm">
                @foreach ($stay->guests as $guest)
                    <li>
                        {{ $guest->first_name }} {{ $guest->last_name }}
                        <span class="text-gray-500">· {{ $guest->document_type->etiqueta() }} {{ $guest->issuing_country }}</span>
                        @if ($guest->is($primaryGuest))
                            <x-badge>Principal</x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Saldo">
            <p @class(['text-3xl font-semibold', 'text-red-700' => $balance > 0, 'text-green-700' => $balance < 0])>
                Bs {{ $balance > 0 ? $balance : ($balance < 0 ? $balance * -1 : '0.00') }}
            </p>
            <p class="text-sm text-gray-600">
                @if ($balance > 0)
                    Pendiente de pago.
                @elseif ($balance < 0)
                    A favor del huésped: se devuelve al registrar la salida.
                @else
                    La cuenta está saldada.
                @endif
            </p>

            @if ($isActive)
                @php($canLeaveWithDebt = $openCheckoutException?->estaVigente() && $checkoutExceptionMatchesDebt)
                @if ($balance > 0)
                    @include('stays.partials.checkout-exception')
                @endif
                <div class="flex flex-col gap-3 border-t border-gray-100 pt-4">
                    @can('registrarSalida', $stay)
                        <form method="POST" action="{{ route('stays.check-out', $stay) }}" class="flex flex-col gap-3">
                            @csrf
                            @if ($balance < 0)
                                <x-select label="Caja para devolver el efectivo" name="cash_session_id"
                                    :options="['' => 'Sin efectivo que devolver'] + $openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all()" />
                            @endif
                            <x-button :disabled="$balance > 0 && ! $canLeaveWithDebt">{{ $balance > 0 ? 'Registrar salida con deuda' : 'Registrar salida' }}</x-button>
                        </form>
                    @endcan
                    @can('cambiarHabitacion', $stay)
                        <a href="{{ route('stays.room-changes.create', $stay) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cambiar de habitación</a>
                    @endcan
                </div>
            @endif
        </x-card>
    </div>

    <x-card title="Cargos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-gray-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Fecha</th>
                        <th class="py-2 pr-4 font-medium">Concepto</th>
                        <th class="py-2 pr-4 text-right font-medium">Cantidad</th>
                        <th class="py-2 pr-4 text-right font-medium">Importe</th>
                        <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($stay->charges as $stayCharge)
                        @php($isVoided = $stayCharge->status === \App\Enums\ChargeStatus::Voided)
                        <tr @class(['text-gray-400 line-through' => $isVoided])>
                            <td class="py-2 pr-4">{{ $stayCharge->service_date->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4">
                                {{ $stayCharge->charge_type->etiqueta() }} · {{ $stayCharge->description }}
                                @if ($isVoided)
                                    <span class="block no-underline">Anulado: {{ $stayCharge->void_reason }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-right">{{ $stayCharge->quantity }}</td>
                            <td class="py-2 pr-4 text-right">
                                {{ $stayCharge->direction === \App\Enums\ChargeDirection::Credit ? '−' : '' }}Bs {{ $stayCharge->total_amount }}
                            </td>
                            <td class="py-2">
                                @if (! $isVoided && $isActive)
                                    @can('anular', $stayCharge)
                                        <form method="POST" action="{{ route('stay-charges.void', $stayCharge) }}" class="flex gap-2">
                                            @csrf
                                            <label class="sr-only" for="void-reason-{{ $stayCharge->id }}">Motivo de la anulación</label>
                                            <input id="void-reason-{{ $stayCharge->id }}" name="void_reason" required placeholder="Motivo"
                                                class="w-32 rounded-md border border-gray-300 px-2 py-1 text-sm">
                                            <x-button variant="secondary" class="py-1">Anular</x-button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">Sin cargos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Pagos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-gray-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Fecha</th>
                        <th class="py-2 pr-4 font-medium">Tipo</th>
                        <th class="py-2 pr-4 font-medium">Método</th>
                        <th class="py-2 pr-4 font-medium">Caja</th>
                        <th class="py-2 pr-4 text-right font-medium">Importe</th>
                        <th class="py-2 pr-4 text-right font-medium">Devuelto</th>
                        <th class="py-2 pr-4 font-medium">Recibo</th>
                        <th class="py-2 font-medium">Factura externa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 align-top">
                    @forelse ($stay->payments as $payment)
                        @php($issuedReceipt = $payment->receipts->first())
                        <tr>
                            <td class="py-2 pr-4">{{ $payment->paid_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">{{ $payment->payment_type->etiqueta() }}</td>
                            <td class="py-2 pr-4">{{ $payment->payment_method->etiqueta() }}{{ $payment->reference ? " · {$payment->reference}" : '' }}</td>
                            <td class="py-2 pr-4">{{ $payment->cashSession->cashRegister->name }}</td>
                            <td class="py-2 pr-4 text-right">Bs {{ $payment->amount }}</td>
                            <td class="py-2 pr-4 text-right">{{ $payment->refunded_amount ? "Bs {$payment->refunded_amount}" : '' }}</td>
                            <td class="py-2 pr-4">
                                @if ($issuedReceipt)
                                    <a href="{{ route('receipts.show', $issuedReceipt) }}" class="underline">{{ $issuedReceipt->numeroCompleto() }}</a>
                                @elseif ($payment->status === \App\Enums\PaymentStatus::Confirmed)
                                    @can('emitir', [\App\Models\Receipt::class, $payment])
                                        <a href="{{ route('payments.receipts.create', $payment) }}" class="underline">Emitir recibo</a>
                                    @endcan
                                @endif
                            </td>
                            <td class="py-2">
                                @can('registrarFacturaExterna', $payment)
                                    <form method="POST" action="{{ route('payments.external-invoice.update', $payment) }}" class="flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="external-invoice-{{ $payment->id }}">Número de factura</label>
                                        <input id="external-invoice-{{ $payment->id }}" name="external_invoice_number" value="{{ $payment->external_invoice_number }}" placeholder="N.º de factura"
                                            class="w-28 rounded-md border border-gray-300 px-2 py-1 text-sm">
                                        <x-button variant="secondary" class="py-1">Guardar</x-button>
                                    </form>
                                @else
                                    {{ $payment->external_invoice_number }}
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-4 text-gray-500">Sin pagos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($isActive)
        <div class="grid gap-4 lg:grid-cols-3">
            @include('stays.partials.payment-form', ['settlementOnly' => false])

            @can('registrarCargo', $stay)
                <x-card title="Agregar cargo">
                    <form method="POST" action="{{ route('stays.charges.store', $stay) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-select label="Tipo" name="charge_type"
                            :options="collect($manualChargeTypes)->mapWithKeys(fn ($chargeType) => [$chargeType->value => $chargeType->etiqueta()])" />
                        <x-field label="Descripción" name="description" required />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="Cantidad" name="quantity" value="1" inputmode="decimal" required />
                            <x-field label="Precio unitario (Bs)" name="unit_price" inputmode="decimal" required />
                        </div>
                        <x-button>Agregar cargo</x-button>
                    </form>
                </x-card>
            @endcan

            @if ($balance < 0 && $refundablePayments->isNotEmpty())
                @can('devolver', $refundablePayments->first())
                    <x-card title="Devolver saldo a favor">
                        <form method="POST" action="{{ route('stays.refunds.store', $stay) }}" class="flex flex-col gap-3">
                            @csrf
                            <input type="hidden" name="refund_operation_uuid" value="{{ old('refund_operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                            <x-select label="Pago a devolver" name="refund_payment_id"
                                :options="$refundablePayments->mapWithKeys(fn ($payment) => [$payment->id => $payment->payment_method->etiqueta().' · '.$payment->paid_at->timezone($activeBranch->timezone)->format('d/m H:i').' · Bs '.$payment->refundable_amount])" />
                            <x-select label="Caja (solo si se devuelve efectivo)" name="refund_cash_session_id"
                                :options="['' => 'Sin efectivo'] + $openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all()" />
                            <x-field label="Importe (Bs)" name="refund_amount" inputmode="decimal" :value="(string) ($balance * -1)" required />
                            <x-field label="Motivo" name="refund_reason" required />
                            <x-button variant="secondary">Devolver</x-button>
                        </form>
                    </x-card>
                @endcan
            @endif

            @can('registrarDescuento', $stay)
                <x-card title="Aplicar descuento">
                    <form method="POST" action="{{ route('stays.discounts.store', $stay) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-field label="Motivo" name="discount_description" required />
                        <x-field label="Importe (Bs)" name="discount_amount" inputmode="decimal" required />
                        <x-button>Aplicar descuento</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    @elseif ($stay->status === \App\Enums\StayStatus::Completed && $balance > 0)
        <div class="grid gap-4 lg:grid-cols-3">
            @include('stays.partials.payment-form', ['settlementOnly' => true])
        </div>
    @endif
</x-layouts.app>
