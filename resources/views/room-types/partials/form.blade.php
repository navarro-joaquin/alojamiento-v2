{{-- Expects: $roomType --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Nombre" name="name" :value="$roomType->name" required />
        <x-field label="Capacidad habitual" name="default_capacity" type="number" min="1" max="20" :value="$roomType->default_capacity" required />
    </div>
    <label class="flex flex-col gap-1 text-sm font-medium">
        Descripción
        <textarea name="description" rows="2" class="rounded-lg border border-gray-300 px-3 py-2 font-normal">{{ old('description', $roomType->description) }}</textarea>
    </label>
</x-card>
