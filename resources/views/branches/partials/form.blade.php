{{-- Expects: $branch --}}
<x-card title="Datos generales">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Código" name="code" :value="$branch->code" maxlength="20" required />
        <x-field label="Nombre" name="name" :value="$branch->name" required />
        <x-field label="Dirección" name="address" :value="$branch->address" />
        <x-field label="Teléfono" name="phone" type="tel" :value="$branch->phone" />
        <x-field label="Zona horaria" name="timezone" :value="$branch->timezone" required />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active)) class="rounded border-gray-300">
        Sucursal activa
    </label>
</x-card>

<x-card title="Política de la sucursal">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Hora de ingreso" name="check_in_time" type="time" :value="substr((string) $branch->check_in_time, 0, 5)" required />
        <x-field label="Hora de salida" name="check_out_time" type="time" :value="substr((string) $branch->check_out_time, 0, 5)" required />
        <x-field label="Noches de anticipo máximo" name="deposit_limit_nights" type="number" min="0" :value="$branch->deposit_limit_nights" />
        <x-field label="Umbral de diferencia de caja (Bs)" name="cash_difference_threshold" inputmode="decimal" :value="$branch->cash_difference_threshold" />
        <x-field label="Umbral de aprobación de gastos (Bs)" name="expense_approval_threshold" inputmode="decimal" :value="$branch->expense_approval_threshold" />
    </div>
    <p class="text-sm text-gray-600">
        Deje los umbrales y las noches de anticipo vacíos para usar los valores globales:
        Bs {{ config('lodging.cash_difference_threshold') }} de diferencia de caja, Bs {{ config('lodging.expense_approval_threshold') }} para gastos y {{ config('lodging.deposit_limit_nights') }} noches.
    </p>
</x-card>
