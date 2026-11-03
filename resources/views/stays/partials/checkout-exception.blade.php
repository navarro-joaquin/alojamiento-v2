{{-- Expects: $stay, $balance, $openCheckoutException, $checkoutExceptionMatchesDebt, $canLeaveWithDebt --}}
<div class="flex flex-col gap-3 border-t border-gray-100 pt-4 text-sm">
    <p class="font-medium">Salida con deuda</p>

    @if ($openCheckoutException?->status === \App\Enums\CheckoutExceptionStatus::Requested)
        <p class="text-gray-600">
            <x-badge color="amber">Pendiente de aprobación</x-badge>
            Bs {{ $openCheckoutException->outstanding_amount }}, solicitada por {{ $openCheckoutException->requestedByUser->name }}: {{ $openCheckoutException->reason }}
        </p>
        @can('aprobar', $openCheckoutException)
            <form method="POST" action="{{ route('checkout-exceptions.approve', $openCheckoutException) }}">
                @csrf
                <x-button>Autorizar salida con deuda</x-button>
            </form>
        @endcan
    @elseif ($canLeaveWithDebt)
        <p class="text-gray-600">
            <x-badge color="green">Autorizada</x-badge>
            Bs {{ $openCheckoutException->outstanding_amount }} por {{ $openCheckoutException->approvedByUser->name }},
            hasta las {{ $openCheckoutException->expires_at->timezone($activeBranch->timezone)->format('H:i') }}. La salida la registra otro operador.
        </p>
    @else
        @if ($openCheckoutException !== null)
            <p class="text-gray-600">
                <x-badge>{{ $openCheckoutException->estaVigente() ? 'Deuda distinta' : 'Vencida' }}</x-badge>
                La autorización de Bs {{ $openCheckoutException->outstanding_amount }} ya no sirve: hay que solicitar una nueva.
            </p>
        @endif
        @can('solicitarSalidaConDeuda', $stay)
            <form method="POST" action="{{ route('stays.checkout-exceptions.store', $stay) }}" class="flex flex-col gap-3">
                @csrf
                <x-field label="Motivo de la salida con deuda" name="exception_reason" required />
                <x-button variant="secondary">Solicitar autorización por Bs {{ $balance }}</x-button>
            </form>
        @endcan
    @endif

    @if ($openCheckoutException !== null)
        @can('rechazar', $openCheckoutException)
            <form method="POST" action="{{ route('checkout-exceptions.reject', $openCheckoutException) }}" class="flex flex-col gap-2">
                @csrf
                <x-field label="Motivo del rechazo" name="rejection_notes" required />
                <x-button variant="secondary">{{ $openCheckoutException->status === \App\Enums\CheckoutExceptionStatus::Requested ? 'Rechazar' : 'Revocar' }}</x-button>
            </form>
        @endcan
    @endif
</div>
