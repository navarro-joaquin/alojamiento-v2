@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center gap-6 bg-ink-800 px-4 text-gray-900 antialiased">
    <p class="font-serif text-3xl font-semibold tracking-tight text-white">{{ config('app.name') }}</p>
    <main class="flex w-full max-w-md flex-col gap-6 rounded-xl bg-paper p-8 shadow-xl shadow-ink-950/30">
        <header class="flex flex-col gap-3">
            <h1 class="font-serif text-2xl font-semibold text-ink-900">{{ $title }}</h1>
            <div class="guilloche-rule" aria-hidden="true"></div>
        </header>
        {{ $slot }}
    </main>
</body>
</html>
