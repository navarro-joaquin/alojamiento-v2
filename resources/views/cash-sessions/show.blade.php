@php($isOpen = $cashSession->status->aceptaMovimientos())

<x-layouts.app title="Sesión de {{ $cashSession->cashRegister->name }}">
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Resumen" class="lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Estado</dt>
                    <dd><x-badge :color="$cashSession->status->color()">{{ $cashSession->status->etiqueta() }}</x-badge></dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Apertura</dt>
                    <dd>{{ $cashSession->opened_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }} · {{ $cashSession->openedByUser->name }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-gray-500">Fondo inicial</dt>
                    <dd>Bs {{ $cashSession->opening_amount }}</dd>
                </div>
                @unless ($isOpen)
                    <div class="flex flex-col gap-1">
                        <dt class="text-gray-500">Cierre</dt>
                        <dd>{{ $cashSession->closed_at?->timezone($activeBranch->timezone)->format('d/m/Y H:i') }} · {{ $cashSession->closedByUser?->name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-gray-500">Efectivo esperado / contado</dt>
                        <dd>Bs {{ $cashSession->expected_amount }} / Bs {{ $cashSession->declared_amount }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-gray-500">Diferencia</dt>
                        <dd @class(['font-semibold', 'text-red-700' => $cashSession->difference_amount !== '0.00'])>Bs {{ $cashSession->difference_amount }}</dd>
                    </div>
                @endunless
            </dl>

            <div class="flex flex-col gap-2 border-t border-gray-100 pt-4">
                <h3 class="text-sm font-medium">Neto por método</h3>
                @forelse ($netByPaymentMethod as $methodLabel => $netAmount)
                    <p class="flex justify-between text-sm"><span>{{ $methodLabel }}</span><span>Bs {{ $netAmount }}</span></p>
                @empty
                    <p class="text-sm text-gray-600">Sin movimientos.</p>
                @endforelse
                <p class="text-xs text-gray-500">Solo el efectivo cuenta para el efectivo esperado; tarjeta, transferencia y QR se concilian aparte.</p>
            </div>
        </x-card>

        <x-card title="{{ $isOpen ? 'Efectivo esperado' : 'Sesión cerrada' }}">
            @if ($isOpen)
                <p class="figures text-4xl text-ink-900">Bs {{ $expectedCash }}</p>
                <p class="text-sm text-gray-600">Fondo inicial más entradas y menos salidas en efectivo.</p>

                @can('cerrar', $cashSession)
                    <form method="POST" action="{{ route('cash-sessions.close', $cashSession) }}" class="flex flex-col gap-3 border-t border-gray-100 pt-4">
                        @csrf
                        <x-field label="Efectivo contado (Bs)" name="declared_amount" inputmode="decimal" />
                        @include('cash-sessions.partials.denominations', ['fieldName' => 'denominations'])
                        <label class="flex flex-col gap-1 text-sm font-medium">
                            Observaciones
                            <textarea name="closing_notes" rows="2" class="rounded-lg border border-gray-300 px-3 py-2 font-normal">{{ old('closing_notes') }}</textarea>
                        </label>
                        <x-button>Cerrar caja</x-button>
                    </form>
                @endcan
            @else
                <p class="text-sm text-gray-600">{{ $cashSession->closing_notes ?: 'Sin observaciones.' }}</p>

                @if ($cashSession->reviewedByUser)
                    <div class="flex flex-col gap-1 border-t border-gray-100 pt-4 text-sm">
                        <p class="font-medium">Revisada por {{ $cashSession->reviewedByUser->name }} el {{ $cashSession->reviewed_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</p>
                        <p class="text-gray-600">{{ $cashSession->review_notes }}</p>
                    </div>
                @endif

                @if ($cashSession->status === \App\Enums\CashSessionStatus::PendingReview)
                    @can('revisar', $cashSession)
                        <form method="POST" action="{{ route('cash-sessions.review', $cashSession) }}" class="flex flex-col gap-3 border-t border-gray-100 pt-4">
                            @csrf
                            <p class="text-sm text-gray-600">La diferencia supera el umbral de la sucursal. Revísela y deje constancia de lo que se hizo.</p>
                            <label class="flex flex-col gap-1 text-sm font-medium">
                                Observaciones de la revisión
                                <textarea name="review_notes" rows="2" required class="rounded-lg border border-gray-300 px-3 py-2 font-normal">{{ old('review_notes') }}</textarea>
                            </label>
                            <x-button>Dar por revisada</x-button>
                        </form>
                    @else
                        <p class="border-t border-gray-100 pt-4 text-sm text-gray-600">Pendiente de que la revise un supervisor distinto de quien la cerró.</p>
                    @endcan
                @endif
            @endif
        </x-card>
    </div>

    <x-card title="Arqueos">
        @if ($cashSession->cashCounts->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay arqueos.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Hora</th>
                            <th class="py-2 pr-4 font-semibold">Tipo</th>
                            <th class="py-2 pr-4 font-semibold">Usuario</th>
                            <th class="py-2 pr-4 text-right font-semibold">Esperado</th>
                            <th class="py-2 pr-4 text-right font-semibold">Contado</th>
                            <th class="py-2 pr-4 text-right font-semibold">Diferencia</th>
                            <th class="py-2 font-semibold">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($cashSession->cashCounts as $cashCount)
                            <tr>
                                <td class="py-2 pr-4">{{ $cashCount->counted_at->timezone($activeBranch->timezone)->format('d/m H:i') }}</td>
                                <td class="py-2 pr-4">{{ $cashCount->count_type->etiqueta() }}</td>
                                <td class="py-2 pr-4">{{ $cashCount->countedByUser->name }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $cashCount->expected_amount }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $cashCount->declared_amount }}</td>
                                <td @class(['py-2 pr-4 text-right', 'font-semibold text-red-700' => $cashCount->difference_amount !== '0.00'])>Bs {{ $cashCount->difference_amount }}</td>
                                <td class="py-2 text-gray-600">{{ $cashCount->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($isOpen)
            @can('registrarArqueo', $cashSession)
                <form method="POST" action="{{ route('cash-sessions.counts.store', $cashSession) }}" class="flex flex-col gap-3 border-t border-gray-100 pt-4">
                    @csrf
                    <p class="text-sm text-gray-600">Un arqueo parcial (por ejemplo, en el cambio de turno) registra la diferencia del momento sin cerrar la caja ni mover dinero.</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Efectivo contado (Bs)" name="count_declared_amount" inputmode="decimal" />
                        <x-field label="Observaciones" name="count_notes" />
                    </div>
                    @include('cash-sessions.partials.denominations', ['fieldName' => 'count_denominations'])
                    <div>
                        <x-button variant="secondary">Registrar arqueo</x-button>
                    </div>
                </form>
            @endcan
        @endif
    </x-card>

    <x-card title="Movimientos">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-ink-600">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Hora</th>
                        <th class="py-2 pr-4 font-semibold">Tipo</th>
                        <th class="py-2 pr-4 font-semibold">Descripción</th>
                        <th class="py-2 pr-4 font-semibold">Método</th>
                        <th class="py-2 pr-4 font-semibold">Usuario</th>
                        <th class="py-2 text-right font-semibold">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($cashSession->movements as $movement)
                        <tr>
                            <td class="py-2 pr-4">{{ $movement->occurred_at->timezone($activeBranch->timezone)->format('d/m H:i') }}</td>
                            <td class="py-2 pr-4">{{ $movement->movement_type->etiqueta() }}</td>
                            <td class="py-2 pr-4">{{ $movement->description }}</td>
                            <td class="py-2 pr-4">{{ $movement->payment_method->etiqueta() }}</td>
                            <td class="py-2 pr-4">{{ $movement->performedByUser->name }}</td>
                            <td @class(['py-2 text-right', 'text-red-700' => $movement->direction === \App\Enums\CashMovementDirection::Out])>
                                {{ $movement->direction === \App\Enums\CashMovementDirection::Out ? '−' : '' }}Bs {{ $movement->amount }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-gray-500">Sin movimientos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
