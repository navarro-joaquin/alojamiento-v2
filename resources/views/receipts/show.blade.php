@php($isVoided = $receipt->status === \App\Enums\ReceiptStatus::Voided)

<x-layouts.app title="Recibo {{ $receipt->numeroCompleto() }}">
    <x-card class="max-w-2xl">
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1 text-sm">
                <p class="text-base font-semibold">{{ $receipt->branch->name }}</p>
                <p class="text-gray-600">{{ $receipt->branch->address }}</p>
                @if ($receipt->branch->phone)
                    <p class="text-gray-600">Tel. {{ $receipt->branch->phone }}</p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-1 text-sm">
                <p class="text-base font-semibold">Recibo N.º {{ $receipt->numeroCompleto() }}</p>
                <p>{{ $receipt->issued_at->timezone($receipt->branch->timezone)->format('d/m/Y H:i') }}</p>
                <x-badge :color="$receipt->status->color()">{{ $receipt->status->etiqueta() }}</x-badge>
            </div>
        </div>

        <dl class="grid gap-3 border-t border-gray-100 pt-4 text-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <dt class="text-gray-500">Recibimos de</dt>
                <dd>{{ $receipt->holder_name }}{{ $receipt->holder_document ? " · {$receipt->holder_document}" : '' }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-gray-500">Concepto</dt>
                <dd>{{ $receipt->payment->payment_type->etiqueta() }} por alojamiento, habitación {{ $receipt->stay->room->room_number }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-gray-500">Forma de pago</dt>
                <dd>{{ $receipt->payment->payment_method->etiqueta() }}{{ $receipt->payment->reference ? " · {$receipt->payment->reference}" : '' }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-gray-500">Importe</dt>
                <dd @class(['text-lg font-semibold', 'line-through' => $isVoided])>Bs {{ $receipt->amount }}</dd>
            </div>
        </dl>

        <p class="border-t border-gray-100 pt-4 text-xs text-gray-500">
            Emitido por {{ $receipt->issuedByUser->name }}. Recibo interno: no es una factura.
            @if ($receipt->payment->external_invoice_number)
                Factura N.º {{ $receipt->payment->external_invoice_number }}.
            @endif
        </p>

        @if ($isVoided)
            <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                Anulado por {{ $receipt->voidedByUser->name }} el {{ $receipt->voided_at->timezone($receipt->branch->timezone)->format('d/m/Y H:i') }}: {{ $receipt->void_reason }}
            </p>
        @endif
    </x-card>

    <div class="flex flex-wrap items-start gap-4 print:hidden">
        @unless ($isVoided)
            <button type="button" onclick="window.print()" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Imprimir</button>
        @endunless
        <a href="{{ route('stays.show', $receipt->stay) }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Volver a la estadía</a>

        @unless ($isVoided)
            @can('anular', $receipt)
                <form method="POST" action="{{ route('receipts.void', $receipt) }}" class="flex gap-2">
                    @csrf
                    <label class="sr-only" for="void-reason">Motivo de la anulación</label>
                    <input id="void-reason" name="void_reason" required placeholder="Motivo de la anulación"
                        class="w-64 rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <x-button variant="danger">Anular recibo</x-button>
                </form>
            @endcan
        @endunless
    </div>
</x-layouts.app>
