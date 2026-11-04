{{-- Expects: $productCategory --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Nombre" name="name" :value="$productCategory->name" required />
        <x-field label="Descripción" name="description" :value="$productCategory->description" />
    </div>
</x-card>
