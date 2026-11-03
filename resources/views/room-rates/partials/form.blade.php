{{-- Expects: $roomRate, $roomTypes --}}
@php($selectedDays = array_map('intval', old('days_of_week', $roomRate->days_of_week ?? [])))

<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-select label="Tipo de habitación" name="room_type_id" :options="$roomTypes->pluck('name', 'id')" :selected="$roomRate->room_type_id" required />
        <x-field label="Nombre" name="name" :value="$roomRate->name" required />
        <x-field label="Importe por noche (Bs)" name="amount" inputmode="decimal" :value="$roomRate->amount" required />
        <x-field label="Huéspedes (vacío = cualquiera)" name="occupancy" type="number" min="1" max="20" :value="$roomRate->occupancy" />
        <x-field label="Prioridad" name="priority" type="number" min="-100" max="100" :value="$roomRate->priority" required />
        <div></div>
        <x-field label="Vigente desde" name="valid_from" type="date" :value="$roomRate->valid_from?->format('Y-m-d')" required />
        <x-field label="Vigente hasta (vacío = sin fin)" name="valid_to" type="date" :value="$roomRate->valid_to?->format('Y-m-d')" />
    </div>

    <fieldset class="flex flex-col gap-2 text-sm">
        <legend class="font-medium">Días de la semana (ninguno = todos)</legend>
        <div class="flex flex-wrap gap-4">
            @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $day => $dayName)
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="days_of_week[]" value="{{ $day }}" @checked(in_array($day, $selectedDays, true)) class="rounded border-gray-300">
                    {{ $dayName }}
                </label>
            @endforeach
        </div>
        @error('days_of_week')
            <span class="text-red-700">{{ $message }}</span>
        @enderror
    </fieldset>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $roomRate->is_active)) class="rounded border-gray-300">
        Tarifa activa
    </label>
    <p class="text-sm text-gray-600">Cambiar una tarifa solo afecta a los ingresos posteriores: cada estadía conserva el importe acordado.</p>
</x-card>
