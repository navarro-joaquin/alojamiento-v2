{{-- Expects: $fixedAsset; on registration also $roomOptions and $statuses --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Código" name="asset_code" :value="$fixedAsset->asset_code" required />
        <x-field label="Nombre" name="name" :value="$fixedAsset->name" required />
        <x-field label="Número de serie" name="serial_number" :value="$fixedAsset->serial_number" />
        <x-field label="Fecha de compra" name="purchase_date" type="date" :value="$fixedAsset->purchase_date?->toDateString()" />
        <x-field label="Costo de compra (Bs)" name="purchase_cost" inputmode="decimal" :value="$fixedAsset->purchase_cost" />
    </div>
    @isset($statuses)
        <div class="grid gap-4 sm:grid-cols-3">
            <x-select label="Estado inicial" name="status" :selected="$fixedAsset->status->value"
                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->etiqueta()])->all()" />
            <x-select label="Habitación (solo si está en uso)" name="room_id" :options="$roomOptions" />
        </div>
    @endisset
</x-card>
