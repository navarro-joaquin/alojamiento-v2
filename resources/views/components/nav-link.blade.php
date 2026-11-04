@props(['href', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class([
    'rounded-md px-3 py-2 text-sm',
    'bg-gray-100 font-medium text-gray-900' => $active,
    'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $active,
]) }}>{{ $slot }}</a>
