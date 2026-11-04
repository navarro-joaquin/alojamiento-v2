<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Categories are deactivated rather than deleted, so past expenses keep them.
 */
class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', ExpenseCategory::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(ExpenseCategory::class)->ignore($this->route('expenseCategory'))],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
