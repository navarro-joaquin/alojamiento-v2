<x-layouts.app title="Salidas con deuda">
    <x-card>
        @if ($checkoutExceptions->isEmpty())
            <p class="text-sm text-gray-600">No hay salidas con deuda pendientes ni autorizadas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Habitación</th>
                            <th class="py-2 pr-4 font-semibold">Huésped</th>
                            <th class="py-2 pr-4 font-semibold">Solicitud</th>
                            <th class="py-2 pr-4 text-right font-semibold">Deuda solicitada</th>
                            <th class="py-2 pr-4 text-right font-semibold">Deuda actual</th>
                            <th class="py-2 pr-4 font-semibold">Estado</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        @foreach ($checkoutExceptions as $checkoutException)
                            @php($currentDebt = $currentDebts[$checkoutException->id])
                            <tr>
                                <td class="py-2 pr-4 font-medium">
                                    <a href="{{ route('stays.show', $checkoutException->stay) }}" class="underline">{{ $checkoutException->stay->room->room_number }}</a>
                                </td>
                                <td class="py-2 pr-4">{{ $checkoutException->stay->guests->first()?->first_name }} {{ $checkoutException->stay->guests->first()?->last_name }}</td>
                                <td class="py-2 pr-4">
                                    {{ $checkoutException->requestedByUser->name }},
                                    {{ $checkoutException->requested_at->timezone($activeBranch->timezone)->format('d/m H:i') }}
                                    <span class="block text-gray-600">{{ $checkoutException->reason }}</span>
                                </td>
                                <td class="py-2 pr-4 text-right">Bs {{ $checkoutException->outstanding_amount }}</td>
                                <td @class(['py-2 pr-4 text-right', 'text-red-700' => (string) $currentDebt !== $checkoutException->outstanding_amount])>Bs {{ $currentDebt }}</td>
                                <td class="py-2 pr-4">
                                    @if ($checkoutException->status === \App\Enums\CheckoutExceptionStatus::Approved && ! $checkoutException->estaVigente())
                                        <x-badge>Vencida</x-badge>
                                    @else
                                        <x-badge :color="$checkoutException->status->color()">{{ $checkoutException->status->etiqueta() }}</x-badge>
                                    @endif
                                    @if ($checkoutException->approvedByUser)
                                        <span class="block text-gray-600">
                                            {{ $checkoutException->approvedByUser->name }}, hasta las {{ $checkoutException->expires_at->timezone($activeBranch->timezone)->format('H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="flex flex-col gap-2 py-2">
                                    @if ($checkoutException->status === \App\Enums\CheckoutExceptionStatus::Requested)
                                        @can('aprobar', $checkoutException)
                                            <form method="POST" action="{{ route('checkout-exceptions.approve', $checkoutException) }}">
                                                @csrf
                                                <x-button class="py-1">Autorizar</x-button>
                                            </form>
                                        @endcan
                                    @endif
                                    @can('rechazar', $checkoutException)
                                        <form method="POST" action="{{ route('checkout-exceptions.reject', $checkoutException) }}" class="flex gap-2">
                                            @csrf
                                            <label class="sr-only" for="rejection-notes-{{ $checkoutException->id }}">Motivo del rechazo</label>
                                            <input id="rejection-notes-{{ $checkoutException->id }}" name="rejection_notes" required placeholder="Motivo"
                                                class="w-32 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                            <x-button variant="secondary" class="py-1">
                                                {{ $checkoutException->status === \App\Enums\CheckoutExceptionStatus::Requested ? 'Rechazar' : 'Revocar' }}
                                            </x-button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="text-sm text-gray-600">
        Nadie puede autorizar su propia solicitud. Una autorización vale {{ config('lodging.checkout_exception_minutes') }} minutos y solo para la deuda solicitada;
        la salida la registra un operador distinto de quien autorizó.
    </p>
</x-layouts.app>
