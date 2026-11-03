<x-layouts.app title="Ocupación">
    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ($summary as $label => $count)
            <div class="flex flex-col gap-1 rounded-lg border border-gray-200 bg-white p-4">
                <dt class="text-sm text-gray-500">{{ $label }}</dt>
                <dd class="text-2xl font-semibold">{{ $count }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($rooms->isEmpty())
        <p class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-600">La sucursal no tiene habitaciones registradas.</p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($rooms as $room)
                <li class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="text-lg font-semibold">{{ $room->room_number }}</span>
                            <span class="text-sm text-gray-500">{{ $room->roomType->name }} · {{ $room->capacity }} pers.</span>
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
                        <p class="flex flex-col text-sm">
                            <span>{{ $room->activeStay->guests->first()?->first_name }} {{ $room->activeStay->guests->first()?->last_name }}</span>
                            <span class="text-gray-500">Salida prevista: {{ $room->activeStay->expected_check_out_at?->format('d/m/Y') ?? 'sin fecha' }}</span>
                        </p>
                    @endif

                    @can('cambiarEstadoLimpieza', $room)
                        <form method="POST" action="{{ route('rooms.housekeeping-status.update', $room) }}" class="mt-auto flex gap-2">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="housekeeping-{{ $room->id }}">Estado de limpieza</label>
                            <select id="housekeeping-{{ $room->id }}" name="housekeeping_status" class="grow rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                @foreach ($housekeepingStatuses as $housekeepingStatus)
                                    <option value="{{ $housekeepingStatus->value }}" @selected($room->housekeeping_status === $housekeepingStatus)>{{ $housekeepingStatus->etiqueta() }}</option>
                                @endforeach
                            </select>
                            <x-button variant="secondary">Guardar</x-button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
