{{-- Expects: $prefix (form name prefix, e.g. "primary_guest" or "companions[0]"), $documentTypes --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-select label="Tipo de documento" name="{{ $prefix }}[document_type]"
        :options="collect($documentTypes)->mapWithKeys(fn ($documentType) => [$documentType->value => $documentType->etiqueta()])" />
    <x-field label="Número de documento" name="{{ $prefix }}[document_number]" autocomplete="off" />
    <x-field label="Complemento" name="{{ $prefix }}[document_complement]" />
    <x-field label="País emisor (código)" name="{{ $prefix }}[issuing_country]" value="BO" maxlength="2" />
    <x-field label="Nombres" name="{{ $prefix }}[first_name]" />
    <x-field label="Apellidos" name="{{ $prefix }}[last_name]" />
    <x-field label="Nacionalidad (código)" name="{{ $prefix }}[nationality]" value="BO" maxlength="2" />
    <x-field label="Teléfono" name="{{ $prefix }}[phone]" type="tel" />
</div>
