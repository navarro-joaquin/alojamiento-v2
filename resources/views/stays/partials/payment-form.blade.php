{{-- Expects: $stay, $balance, $openCashSessions, $paymentTypes, $paymentMethods, $settlementOnly --}}
@can('cobrarPago', $stay)
    <x-card :title="$settlementOnly ? 'Cobrar deuda pendiente' : 'Cobrar'">
        @if ($openCashSessions->isEmpty())
            <p class="text-sm text-gray-600">No hay ninguna caja abierta en la sucursal.</p>
        @else
            <form method="POST" action="{{ route('stays.payments.store', $stay) }}" class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="operation_uuid" value="{{ old('operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                <x-select label="Caja" name="cash_session_id"
                    :options="$openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])" />
                @if ($settlementOnly)
                    <input type="hidden" name="payment_type" value="{{ \App\Enums\PaymentType::Settlement->value }}">
                @else
                    <x-select label="Tipo" name="payment_type" :selected="$balance > 0 ? 'settlement' : 'deposit'"
                        :options="collect($paymentTypes)->mapWithKeys(fn ($paymentType) => [$paymentType->value => $paymentType->etiqueta()])" />
                @endif
                <x-select label="Método" name="payment_method"
                    :options="collect($paymentMethods)->mapWithKeys(fn ($paymentMethod) => [$paymentMethod->value => $paymentMethod->etiqueta()])" />
                <x-field label="Importe (Bs)" name="amount" inputmode="decimal" :value="$balance > 0 ? (string) $balance : null" required />
                <x-field label="Referencia (tarjeta, transferencia o QR)" name="reference" />
                <x-button>Cobrar</x-button>
            </form>
        @endif
    </x-card>
@endcan
