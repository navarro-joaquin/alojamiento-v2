@props(['label', 'name', 'type' => 'text', 'value' => null])

@php($dottedName = str_replace(['[', ']'], ['.', ''], $name))

<label class="flex flex-col gap-1 text-sm font-medium">
    {{ $label }}
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($dottedName, $value) }}"
        {{ $attributes->class(['rounded-md border px-3 py-2 font-normal', 'border-red-500' => $errors->has($dottedName), 'border-gray-300' => ! $errors->has($dottedName)]) }}>
    @error($dottedName)
        <span class="font-normal text-red-700">{{ $message }}</span>
    @enderror
</label>
