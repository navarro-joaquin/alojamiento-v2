{{-- Expects: $expenseCategory --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Nombre" name="name" :value="$expenseCategory->name" required />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $expenseCategory->is_active)) class="rounded border-gray-300">
        Categoría activa
    </label>
</x-card>
