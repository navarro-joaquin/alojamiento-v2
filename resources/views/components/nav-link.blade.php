@props(['href', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class([
    'rounded-lg px-3 py-2 text-base transition-colors duration-150',
    'bg-denom-700 font-semibold text-white shadow-sm shadow-ink-950/30' => $active,
    'text-ink-100 hover:bg-ink-700 hover:text-white' => ! $active,
]) }}>{{ $slot }}</a>
