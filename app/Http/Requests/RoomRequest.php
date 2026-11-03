<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Rooms always belong to the active branch; the branch is never taken from the form.
 */
class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', [Room::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', Rule::exists(RoomType::class, 'id')],
            'room_number' => [
                'required', 'string', 'max:10',
                Rule::unique(Room::class)->where('branch_id', $this->attributes->get('activeBranch')->id)->ignore($this->route('room')),
            ],
            'floor' => ['nullable', 'string', 'max:10'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'room_type_id' => 'tipo de habitación',
            'room_number' => 'número',
            'floor' => 'piso',
            'capacity' => 'capacidad',
        ];
    }
}
