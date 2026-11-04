<x-layouts.app title="Emitir recibo">
    <form method="POST" action="{{ route('payments.receipts.store', $payment) }}" class="flex flex-col gap-6">
        @csrf
        <x-card>
            <p class="text-sm text-gray-600">
                {{ $payment->payment_type->etiqueta() }} de la habitación {{ $payment->folio->stay->room->room_number }} ·
                {{ $payment->payment_method->etiqueta() }} · Bs {{ $payment->amount }}
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Titular" name="holder_name" :value="$holderName" required />
                <x-field label="Documento del titular (CI o NIT)" name="holder_document" :value="$holderDocument" />
            </div>
            <p class="text-xs text-gray-500">Es un recibo interno con numeración correlativa de la sucursal; la factura fiscal se emite en el sistema externo.</p>
        </x-card>
        <div class="flex gap-3">
            <x-button>Emitir recibo</x-button>
            <a href="{{ route('stays.show', $payment->folio->stay) }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
