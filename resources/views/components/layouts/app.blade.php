@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3">
            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="{{ route('occupancy.index') }}" class="text-base font-semibold">{{ config('app.name') }}</a>
                <a href="{{ route('occupancy.index') }}" @class(['hover:text-gray-900', 'text-gray-900' => request()->routeIs('occupancy.*'), 'text-gray-500' => ! request()->routeIs('occupancy.*')])>Ocupación</a>
                @isset($activeBranch)
                    @can('verCajas', [\App\Models\CashRegister::class, $activeBranch])
                        <a href="{{ route('cash-registers.index') }}" @class(['hover:text-gray-900', 'text-gray-900' => request()->routeIs('cash-*'), 'text-gray-500' => ! request()->routeIs('cash-*')])>Cajas</a>
                    @endcan
                @endisset
            </nav>

            <div class="flex items-center gap-4 text-sm">
                @isset($activeBranch)
                    <a href="{{ route('active-branch.edit') }}" class="rounded-md bg-gray-100 px-2.5 py-1 text-gray-700 hover:bg-gray-200">
                        {{ $activeBranch->name }} · {{ $activeBranch->current_business_date->format('d/m/Y') }}
                    </a>
                @endisset
                <span class="text-gray-500">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-500 hover:text-gray-900">Salir</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6">
        <h1 class="text-xl font-semibold">{{ $title }}</h1>

        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <ul class="flex list-inside list-disc flex-col gap-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
