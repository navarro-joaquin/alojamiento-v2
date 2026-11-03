{{-- Expects: $user --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="Nombre" name="name" :value="$user->name" required />
        <x-field label="Correo" name="email" type="email" :value="$user->email" required />
        <x-field :label="$user->exists ? 'Nueva contraseña (vacío = sin cambios)' : 'Contraseña'" name="password" type="password" autocomplete="new-password" :required="! $user->exists" />
        <x-field label="Repetir contraseña" name="password_confirmation" type="password" autocomplete="new-password" />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-gray-300">
        Cuenta habilitada
    </label>
    @error('is_active')
        <span class="text-sm text-red-700">{{ $message }}</span>
    @enderror
</x-card>
