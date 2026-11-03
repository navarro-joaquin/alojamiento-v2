@props(['variant' => 'primary'])

<button {{ $attributes->merge(['type' => 'submit'])->class([
    'inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900 disabled:opacity-50',
    'bg-gray-900 text-white hover:bg-gray-700' => $variant === 'primary',
    'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $variant === 'secondary',
    'bg-red-600 text-white hover:bg-red-500' => $variant === 'danger',
]) }}>{{ $slot }}</button>
