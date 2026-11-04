<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Warehouses always belong to the active branch and are deactivated rather
 * than deleted, so their movements keep them.
 */
class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', [Warehouse::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Warehouse::class)->where('branch_id', $this->attributes->get('activeBranch')->id)->ignore($this->route('warehouse')),
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
