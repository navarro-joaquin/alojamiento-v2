<x-layouts.app title="Ingreso a la habitación {{ $room->room_number }}">
    <p class="text-sm text-gray-600">
        {{ $room->roomType->name }} · capacidad {{ $room->capacity }} · la primera noche se cobra en la fecha de negocio {{ $room->branch->current_business_date->format('d/m/Y') }}.
    </p>

    <form method="POST" action="{{ route('stays.store', $room) }}" class="flex flex-col gap-6">
        @csrf

        <x-card title="Huésped principal">
            @include('stays.partials.guest-fields', ['prefix' => 'primary_guest'])
        </x-card>

        @if ($room->capacity > 1)
            <x-card title="Acompañantes">
                <p class="text-sm text-gray-600">Deje en blanco el número de documento de las filas que no use.</p>
                @foreach (range(0, $room->capacity - 2) as $index)
                    <fieldset class="flex flex-col gap-2 border-t border-gray-100 pt-4 first-of-type:border-t-0 first-of-type:pt-0">
                        <legend class="sr-only">Acompañante {{ $index + 1 }}</legend>
                        @include('stays.partials.guest-fields', ['prefix' => "companions[{$index}]"])
                    </fieldset>
                @endforeach
            </x-card>
        @endif

        <x-card title="Estadía">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Adultos" name="adults" type="number" value="1" min="1" :max="$room->capacity" />
                <x-field label="Niños" name="children" type="number" value="0" min="0" :max="$room->capacity" />
                <x-field label="Salida prevista" name="expected_check_out_date" type="date" />
            </div>
            <label class="flex flex-col gap-1 text-sm font-medium">
                Observaciones
                <textarea name="notes" rows="2" class="rounded-md border border-gray-300 px-3 py-2 font-normal">{{ old('notes') }}</textarea>
            </label>
        </x-card>

        <div class="flex gap-3">
            <x-button>Registrar ingreso</x-button>
            <a href="{{ route('occupancy.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
