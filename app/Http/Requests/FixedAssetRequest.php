<?php

namespace App\Http\Requests;

use App\Enums\FixedAssetStatus;
use App\Models\FixedAsset;
use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Descriptive data of an asset. On registration it also takes the initial
 * status and room; afterwards they change only through movements.
 */
class FixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', [FixedAsset::class, $this->attributes->get('activeBranch')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'asset_code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(FixedAsset::class)->ignore($this->route('fixedAsset'))],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999'],
        ];

        if ($this->route('fixedAsset') === null) {
            $rules['status'] = ['required', Rule::enum(FixedAssetStatus::class)->only(FixedAssetStatus::operativos())];
            $rules['room_id'] = ['nullable', 'integer', Rule::exists(Room::class, 'id')->where('branch_id', $this->attributes->get('activeBranch')->id)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asset_code' => 'código',
            'serial_number' => 'número de serie',
            'purchase_date' => 'fecha de compra',
            'purchase_cost' => 'costo de compra',
            'status' => 'estado',
            'room_id' => 'habitación',
        ];
    }

    /**
     * Descriptive attributes ready to fill the asset.
     *
     * @return array{asset_code: string, name: string, serial_number: ?string, purchase_date: ?string, purchase_cost: ?string}
     */
    public function datosDelActivo(): array
    {
        return [
            'asset_code' => $this->validated('asset_code'),
            'name' => $this->validated('name'),
            'serial_number' => $this->validated('serial_number'),
            'purchase_date' => $this->validated('purchase_date'),
            'purchase_cost' => $this->validated('purchase_cost') === null ? null : (string) $this->validated('purchase_cost'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['asset_code' => mb_strtoupper(trim((string) $this->input('asset_code')))]);
    }
}
