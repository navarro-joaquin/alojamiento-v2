@props(['title' => null])

<section {{ $attributes->class(['flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4']) }}>
    @if ($title)
        <h2 class="text-base font-semibold">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
