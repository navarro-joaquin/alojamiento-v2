<?php

namespace App\Http\Requests;

use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Rates always belong to the active branch. Weekdays use ISO numbers
 * (1 = Monday … 7 = Sunday); none selected means every day.
 */
class RoomRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', [RoomRate::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', Rule::exists(RoomType::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:999999'],
            'occupancy' => ['nullable', 'integer', 'min:1', 'max:20'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7', 'distinct'],
            'priority' => ['required', 'integer', 'min:-100', 'max:100'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'room_type_id' => 'tipo de habitación',
            'amount' => 'importe',
            'occupancy' => 'ocupación',
            'days_of_week' => 'días',
            'priority' => 'prioridad',
            'valid_from' => 'vigente desde',
            'valid_to' => 'vigente hasta',
        ];
    }

    /**
     * Validated attributes ready to fill the rate, with weekdays sorted as integers.
     *
     * @return array<string, mixed>
     */
    public function datosDeTarifa(): array
    {
        $days = $this->validated('days_of_week');

        return [
            ...$this->validated(),
            'days_of_week' => empty($days) ? null : collect($days)->map(fn ($day): int => (int) $day)->sort()->values()->all(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
