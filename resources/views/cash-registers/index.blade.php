<x-layouts.app title="Cajas">
    @php($canManageRegisters = auth()->user()->can('gestionar', [\App\Models\CashRegister::class, $activeBranch]))

    @if ($canManageRegisters)
        <div>
            <a href="{{ route('cash-registers.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nueva caja</a>
        </div>
    @endif

    @if ($cashRegisters->isEmpty())
        <x-card>
            <p class="text-sm text-gray-600">La sucursal no tiene cajas activas.</p>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cashRegisters as $cashRegister)
                @php($openSession = $cashRegister->sessions->first())
                <x-card :title="$cashRegister->name">
                    @if ($openSession)
                        <p class="flex flex-col gap-1 text-sm">
                            <span><x-badge :color="$openSession->status->color()">{{ $openSession->status->etiqueta() }}</x-badge></span>
                            <span class="text-gray-600">Abierta por {{ $openSession->openedByUser->name }} el {{ $openSession->opened_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</span>
                        </p>
                        <a href="{{ route('cash-sessions.show', $openSession) }}" class="text-sm font-medium text-gray-900 underline">Ver sesión</a>
                    @else
                        <x-badge>Cerrada</x-badge>
                        @can('abrirSesion', $cashRegister)
                            <form method="POST" action="{{ route('cash-sessions.store', $cashRegister) }}" class="flex flex-col gap-3">
                                @csrf
                                <x-field label="Fondo inicial (Bs)" name="opening_amount" inputmode="decimal" value="0.00" required />
                                <x-button>Abrir caja</x-button>
                            </form>
                        @endcan
                    @endif
                    @if ($canManageRegisters)
                        <a href="{{ route('cash-registers.edit', $cashRegister) }}" class="text-sm text-gray-600 underline">Editar caja</a>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($canManageRegisters && $inactiveCashRegisters->isNotEmpty())
        <x-card title="Cajas inactivas">
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($inactiveCashRegisters as $inactiveCashRegister)
                    <li><a href="{{ route('cash-registers.edit', $inactiveCashRegister) }}" class="underline">{{ $inactiveCashRegister->name }}</a></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @can('verPendientesDeRevision', [\App\Models\CashSession::class, $activeBranch])
        <x-card title="Cierres por revisar">
            @if ($sessionsPendingReview->isEmpty())
                <p class="text-sm text-gray-600">No hay cierres pendientes de revisión.</p>
            @else
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($sessionsPendingReview as $pendingSession)
                        <li>
                            <a href="{{ route('cash-sessions.show', $pendingSession) }}" class="underline">{{ $pendingSession->cashRegister->name }}</a>
                            · cerrada por {{ $pendingSession->closedByUser->name }} el {{ $pendingSession->closed_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}
                            · <span class="font-semibold text-red-700">diferencia Bs {{ $pendingSession->difference_amount }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endcan

    <x-card title="Últimos cierres">
        @if ($recentSessions->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay cierres.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Caja</th>
                            <th class="py-2 pr-4 font-medium">Cierre</th>
                            <th class="py-2 pr-4 font-medium">Cerrada por</th>
                            <th class="py-2 pr-4 text-right font-medium">Diferencia</th>
                            <th class="py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentSessions as $closedSession)
                            <tr>
                                <td class="py-2 pr-4"><a href="{{ route('cash-sessions.show', $closedSession) }}" class="underline">{{ $closedSession->cashRegister->name }}</a></td>
                                <td class="py-2 pr-4">{{ $closedSession->closed_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $closedSession->closedByUser?->name }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $closedSession->difference_amount }}</td>
                                <td class="py-2"><x-badge :color="$closedSession->status->color()">{{ $closedSession->status->etiqueta() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
