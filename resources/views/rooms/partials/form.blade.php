{{-- Expects: $room, $roomTypes --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-4">
        <x-field label="Número" name="room_number" :value="$room->room_number" maxlength="10" required />
        <x-field label="Piso" name="floor" :value="$room->floor" maxlength="10" />
        <x-select label="Tipo de habitación" name="room_type_id" :options="$roomTypes->pluck('name', 'id')" :selected="$room->room_type_id" required />
        <x-field label="Capacidad" name="capacity" type="number" min="1" max="20" :value="$room->capacity" required />
    </div>
    <p class="text-sm text-gray-600">El estado de limpieza y el operativo se cambian desde la pantalla de ocupación.</p>
</x-card>
