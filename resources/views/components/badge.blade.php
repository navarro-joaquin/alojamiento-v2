@props(['color' => 'gray'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
    'bg-gray-100 text-gray-700 ring-gray-200' => $color === 'gray',
    'bg-free-50 text-free-800 ring-free-200' => $color === 'green',
    'bg-dirty-50 text-dirty-800 ring-dirty-200' => $color === 'amber',
    'bg-alert-50 text-alert-800 ring-alert-200' => $color === 'red',
    'bg-occupied-50 text-occupied-800 ring-occupied-200' => $color === 'blue',
]) }}>{{ $slot }}</span>
