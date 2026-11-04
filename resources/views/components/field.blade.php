@props(['label', 'name', 'type' => 'text', 'value' => null])

@php($dottedName = str_replace(['[', ']'], ['.', ''], $name))

<label class="flex flex-col gap-1.5 text-sm font-semibold text-gray-700">
    {{ $label }}
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($dottedName, $value) }}"
        {{ $attributes->class(['rounded-lg border px-3 py-2.5 text-base font-normal text-gray-900', 'border-red-600' => $errors->has($dottedName), 'border-gray-300' => ! $errors->has($dottedName)]) }}>
    @error($dottedName)
        <span class="font-normal text-red-700">{{ $message }}</span>
    @enderror
</label>
