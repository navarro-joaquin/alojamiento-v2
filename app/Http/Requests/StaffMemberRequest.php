<?php

namespace App\Http\Requests;

use App\Enums\BranchRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Gives an existing account its role in the active branch. Accounts are
 * created by the super admin; nobody may change their own role.
 */
class StaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionarPersonal', [User::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', Rule::exists(User::class, 'email')],
            'role' => ['required', Rule::enum(BranchRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo',
            'role' => 'rol',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'No hay ninguna cuenta con ese correo: pida al superadministrador que la cree.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('email') === $this->user()->email) {
                    $validator->errors()->add('email', 'No puede cambiar su propio rol.');
                }
            },
        ];
    }

    public function miembro(): User
    {
        return User::query()->where('email', $this->validated('email'))->sole();
    }

    public function rol(): BranchRole
    {
        return BranchRole::from($this->validated('role'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }
}
