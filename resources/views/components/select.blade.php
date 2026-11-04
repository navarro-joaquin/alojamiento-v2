@props(['label', 'name', 'options', 'selected' => null])

@php($dottedName = str_replace(['[', ']'], ['.', ''], $name))

{{-- $options: value => label --}}
<label class="flex flex-col gap-1.5 text-sm font-semibold text-gray-700">
    {{ $label }}
    <select name="{{ $name }}" {{ $attributes->class(['rounded-lg border border-gray-300 px-3 py-2.5 text-base font-normal text-gray-900']) }}>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected((string) old($dottedName, $selected) === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($dottedName)
        <span class="font-normal text-red-700">{{ $message }}</span>
    @enderror
</label>
