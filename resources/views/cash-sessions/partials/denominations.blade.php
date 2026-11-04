{{-- Expects: $fieldName (for example "denominations") --}}
<details class="text-sm">
    <summary class="cursor-pointer text-gray-600">Detallar billetes y monedas (opcional)</summary>
    <div class="mt-3 grid grid-cols-2 gap-3">
        @foreach (\App\Enums\CashDenomination::cases() as $denomination)
            <label class="flex flex-col gap-1 font-medium">
                {{ $denomination->etiqueta() }}
                <input type="number" name="{{ $fieldName }}[{{ $denomination->value }}]" min="0" step="1"
                    value="{{ old("{$fieldName}.{$denomination->value}") }}"
                    class="rounded-lg border border-gray-300 px-2 py-1 font-normal">
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-gray-500">Si detalla billetes y monedas, el efectivo contado es su suma.</p>
</details>
