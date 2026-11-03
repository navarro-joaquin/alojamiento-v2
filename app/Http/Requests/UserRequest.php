<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Creates or updates a user account. The password is required only when
 * creating; leaving it empty on update keeps the current one. The super
 * admin flag is not editable here: only `lodging:super-admin` changes it.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('user'))],
            'password' => [$this->route('user') === null ? 'required' : 'nullable', 'string', Password::defaults(), 'confirmed'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('user')?->is($this->user()) && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'No puede deshabilitar su propia cuenta.');
                }
            },
        ];
    }

    /**
     * Validated attributes without an empty password, so updating the
     * account keeps the current one.
     *
     * @return array{name: string, email: string, is_active: bool, password?: string}
     */
    public function datosDeCuenta(): array
    {
        return collect($this->validated())->reject(fn (mixed $value, string $key): bool => $key === 'password' && blank($value))->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
