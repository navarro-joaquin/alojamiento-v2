<x-layouts.app title="Ocupación">
    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @php($summaryStates = ['Ocupadas' => 'occupied', 'Disponibles' => 'free', 'Sucias' => 'dirty', 'Fuera de servicio' => 'alert'])
        @foreach ($summary as $label => $count)
            @php($summaryState = $count > 0 ? ($summaryStates[$label] ?? null) : null)
            <div @class([
                'flex flex-col gap-1 rounded-xl border bg-paper px-5 py-4 shadow-[0_1px_3px_rgb(28_35_64/0.06)]',
                'border-2 border-alert-600 bg-alert-50' => $summaryState === 'alert',
                'border-gray-200' => $summaryState !== 'alert',
            ])>
                <dt class="text-sm font-medium text-gray-600">{{ $label }}</dt>
                <dd @class([
                    'figures text-4xl leading-none',
                    'text-occupied-800' => $summaryState === 'occupied',
                    'text-free-800' => $summaryState === 'free',
                    'text-dirty-800' => $summaryState === 'dirty',
                    'text-alert-800' => $summaryState === 'alert',
                    'text-ink-900' => $summaryState === null,
                ])>{{ $count }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($rooms->isEmpty())
        <p class="rounded-xl border border-gray-200 bg-paper p-6 text-base text-gray-600">La sucursal no tiene habitaciones registradas.</p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($rooms as $room)
                @php($roomState = match (true) {
                    $room->activeStay !== null => 'occupied',
                    $room->operational_status !== \App\Enums\OperationalStatus::InService => 'alert',
                    $room->estaDisponibleParaAsignar() => 'free',
                    default => 'dirty',
                })
                <li @class([
                    'flex flex-col gap-3 rounded-xl border border-t-[5px] border-gray-200 bg-paper p-5 shadow-[0_1px_3px_rgb(28_35_64/0.06)]',
                    'border-t-occupied-600' => $roomState === 'occupied',
                    'border-t-alert-600' => $roomState === 'alert',
                    'border-t-free-600' => $roomState === 'free',
                    'border-t-dirty-600' => $roomState === 'dirty',
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span @class([
                                'figures text-4xl leading-none',
                                'text-occupied-800' => $roomState === 'occupied',
                                'text-alert-800' => $roomState === 'alert',
                                'text-free-800' => $roomState === 'free',
                                'text-dirty-800' => $roomState === 'dirty',
                            ])>{{ $room->room_number }}</span>
                            <span class="mt-1 text-sm text-gray-600">{{ $room->roomType->name }} · {{ $room->capacity }} pers.</span>
                        </div>
                        @if ($room->activeStay)
                            <x-badge color="blue">Ocupada</x-badge>
                        @elseif ($room->estaDisponibleParaAsignar())
                            <x-badge color="green">Disponible</x-badge>
                        @else
                            <x-badge>No disponible</x-badge>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-badge :color="$room->housekeeping_status->color()">{{ $room->housekeeping_status->etiqueta() }}</x-badge>
                        @unless ($room->operational_status === \App\Enums\OperationalStatus::InService)
                            <x-badge color="red">{{ $room->operational_status->etiqueta() }}</x-badge>
                        @endunless
                    </div>

                    @if ($room->activeStay)
                        <p class="flex flex-col gap-0.5 text-sm">
                            <span class="text-base font-semibold text-gray-900">{{ $room->activeStay->guests->first()?->first_name }} {{ $room->activeStay->guests->first()?->last_name }}</span>
                            <span class="text-gray-600">Salida prevista: {{ $room->activeStay->expected_check_out_at?->timezone($activeBranch->timezone)->format('d/m/Y') ?? 'sin fecha' }}</span>
                        </p>
                        <a href="{{ route('stays.show', $room->activeStay) }}" class="self-start rounded-lg border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-800 hover:border-ink-300 hover:bg-ink-50">Ver estadía</a>
                    @elseif ($room->estaDisponibleParaAsignar())
                        @can('registrarIngreso', $room)
                            <a href="{{ route('stays.create', $room) }}" class="self-start rounded-lg bg-denom-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-denom-800">Registrar ingreso</a>
                        @endcan
                    @endif

                    @can('cambiarEstadoLimpieza', $room)
                        <form method="POST" action="{{ route('rooms.housekeeping-status.update', $room) }}" class="mt-auto flex gap-2 border-t border-gray-100 pt-3">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="housekeeping-{{ $room->id }}">Estado de limpieza</label>
                            <select id="housekeeping-{{ $room->id }}" name="housekeeping_status" class="min-w-0 grow rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($housekeepingStatuses as $housekeepingStatus)
                                    <option value="{{ $housekeepingStatus->value }}" @selected($room->housekeeping_status === $housekeepingStatus)>{{ $housekeepingStatus->etiqueta() }}</option>
                                @endforeach
                            </select>
                            <x-button variant="secondary">Guardar</x-button>
                        </form>
                    @endcan

                    @can('cambiarEstadoOperativo', $room)
                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium text-gray-600 hover:text-ink-800">Estado operativo</summary>
                            <form method="POST" action="{{ route('rooms.operational-status.update', $room) }}" class="mt-2 flex flex-col gap-2">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="operational-{{ $room->id }}">Estado operativo</label>
                                <select id="operational-{{ $room->id }}" name="operational_status" class="rounded-lg border border-gray-300 px-2 py-1.5">
                                    @foreach ($operationalStatuses as $operationalStatus)
                                        <option value="{{ $operationalStatus->value }}" @selected($room->operational_status === $operationalStatus)>{{ $operationalStatus->etiqueta() }}</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="operational-reason-{{ $room->id }}">Motivo</label>
                                <input id="operational-reason-{{ $room->id }}" name="reason" placeholder="Motivo (obligatorio si sale de servicio)" class="rounded-lg border border-gray-300 px-2 py-1.5">
                                <x-button variant="secondary">Guardar</x-button>
                            </form>
                        </details>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
