@props(['variant' => 'primary'])

<button {{ $attributes->merge(['type' => 'submit'])->class([
    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50',
    'bg-denom-700 text-white shadow-sm shadow-denom-900/20 hover:bg-denom-800' => $variant === 'primary',
    'border border-gray-300 bg-white text-ink-800 hover:border-ink-300 hover:bg-ink-50' => $variant === 'secondary',
    'bg-red-700 text-white shadow-sm shadow-red-900/20 hover:bg-red-800' => $variant === 'danger',
]) }}>{{ $slot }}</button>
