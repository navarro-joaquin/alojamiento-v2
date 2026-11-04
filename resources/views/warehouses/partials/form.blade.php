{{-- Expects: $warehouse --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Nombre" name="name" :value="$warehouse->name" required />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active)) class="rounded border-gray-300">
        Almacén activo
    </label>
</x-card>
