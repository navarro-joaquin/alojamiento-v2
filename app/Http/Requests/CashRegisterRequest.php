<?php

namespace App\Http\Requests;

use App\Models\CashRegister;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registers always belong to the active branch. A register with an open
 * session cannot be deactivated until the session is closed.
 */
class CashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', [CashRegister::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(CashRegister::class)->where('branch_id', $this->attributes->get('activeBranch')->id)->ignore($this->route('cashRegister')),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cashRegister = $this->route('cashRegister');

                if ($cashRegister !== null && ! $this->boolean('is_active') && $cashRegister->sessions()->abiertas()->exists()) {
                    $validator->errors()->add('is_active', 'La caja tiene una sesión abierta: ciérrela antes de desactivarla.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
