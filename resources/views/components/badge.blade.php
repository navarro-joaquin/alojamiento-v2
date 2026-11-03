@props(['color' => 'gray'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => $color === 'gray',
    'bg-green-100 text-green-800' => $color === 'green',
    'bg-amber-100 text-amber-800' => $color === 'amber',
    'bg-red-100 text-red-800' => $color === 'red',
    'bg-blue-100 text-blue-800' => $color === 'blue',
]) }}>{{ $slot }}</span>
