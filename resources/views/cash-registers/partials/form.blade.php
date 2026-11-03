{{-- Expects: $cashRegister --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Nombre" name="name" :value="$cashRegister->name" required />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $cashRegister->is_active)) class="rounded border-gray-300">
        Caja activa
    </label>
    @error('is_active')
        <span class="text-sm text-red-700">{{ $message }}</span>
    @enderror
</x-card>
