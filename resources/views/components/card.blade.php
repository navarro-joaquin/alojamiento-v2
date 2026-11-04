@props(['title' => null])

<section {{ $attributes->class(['flex flex-col gap-4 rounded-xl border border-gray-200 bg-paper p-5 shadow-[0_1px_3px_rgb(28_35_64/0.06)]']) }}>
    @if ($title)
        <h2 class="font-serif text-xl font-semibold text-ink-900">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
