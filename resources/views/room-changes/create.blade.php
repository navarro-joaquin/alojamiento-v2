<x-layouts.app title="Cambio de habitación · {{ $stay->room->room_number }}">
    @if ($availableRooms->isEmpty())
        <x-card>
            <p class="text-sm text-gray-600">No hay habitaciones libres, limpias y en servicio en la sucursal.</p>
            <a href="{{ route('stays.show', $stay) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Volver a la estadía</a>
        </x-card>
    @else
        <x-card>
            <form method="POST" action="{{ route('stays.room-changes.store', $stay) }}" class="flex max-w-lg flex-col gap-4">
                @csrf
                <x-select label="Nueva habitación" name="room_id"
                    :options="$availableRooms->mapWithKeys(fn ($room) => [$room->id => $room->room_number.' · '.$room->roomType->name.' · '.$room->capacity.' pers.'])" />
                <x-field label="Motivo" name="reason" required />
                <x-field label="Tarifa acordada (Bs, opcional)" name="agreed_rate_amount" inputmode="decimal" />
                <p class="text-sm text-gray-600">Sin tarifa acordada, se aplica la tarifa vigente de la nueva habitación (actual: Bs {{ $stay->rate_amount }}).</p>
                <div class="flex gap-3">
                    <x-button>Cambiar de habitación</x-button>
                    <a href="{{ route('stays.show', $stay) }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                </div>
            </form>
        </x-card>
    @endif
</x-layouts.app>
