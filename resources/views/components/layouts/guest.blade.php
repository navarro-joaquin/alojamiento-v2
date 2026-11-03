@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 text-gray-900 antialiased">
    <main class="flex w-full max-w-sm flex-col gap-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-lg font-semibold">{{ $title }}</h1>
        {{ $slot }}
    </main>
</body>
</html>
