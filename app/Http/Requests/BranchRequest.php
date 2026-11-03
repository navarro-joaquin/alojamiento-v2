<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Creates or updates a branch. The business date is not editable: it starts
 * today in the branch timezone and only the night audit advances it.
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', Branch::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique(Branch::class)->ignore($this->route('branch'))],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'timezone:all'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'cash_difference_threshold' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999'],
            'expense_approval_threshold' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999'],
            'deposit_limit_nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'timezone' => 'zona horaria',
            'check_in_time' => 'hora de ingreso',
            'check_out_time' => 'hora de salida',
            'cash_difference_threshold' => 'umbral de diferencia de caja',
            'expense_approval_threshold' => 'umbral de aprobación de gastos',
            'deposit_limit_nights' => 'noches de anticipo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
