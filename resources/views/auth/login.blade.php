<x-layouts.guest title="Ingresar">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
        @csrf

        <label class="flex flex-col gap-1 text-sm font-medium">
            Correo electrónico
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="rounded-lg border border-gray-300 px-3 py-2 font-normal">
        </label>

        <label class="flex flex-col gap-1 text-sm font-medium">
            Contraseña
            <input type="password" name="password" required autocomplete="current-password"
                class="rounded-lg border border-gray-300 px-3 py-2 font-normal">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
            Mantener la sesión iniciada
        </label>

        @error('email')
            <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        <x-button>Ingresar</x-button>
    </form>
</x-layouts.guest>
