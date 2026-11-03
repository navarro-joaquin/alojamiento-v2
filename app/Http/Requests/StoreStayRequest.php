<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('registrarIngreso', $this->route('room'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->reglasDeHuesped('primary_guest', 'required'),
            'companions' => ['array'],
            ...$this->reglasDeHuesped('companions.*', 'required_with:companions.*.document_number'),
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'expected_check_out_date' => ['nullable', 'date_format:Y-m-d', 'after:'.$this->route('room')->branch->current_business_date->toDateString()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'primary_guest.document_number' => 'número de documento',
            'primary_guest.first_name' => 'nombres',
            'primary_guest.last_name' => 'apellidos',
            'companions.*.first_name' => 'nombres del acompañante',
            'companions.*.last_name' => 'apellidos del acompañante',
            'expected_check_out_date' => 'salida prevista',
            'adults' => 'adultos',
            'children' => 'niños',
        ];
    }

    /**
     * Guest data ready for GuestService::identificar().
     *
     * @return array<string, mixed>
     */
    public function huespedPrincipal(): array
    {
        return $this->validated('primary_guest');
    }

    /**
     * Companions with a document number; blank rows of the form are skipped.
     *
     * @return list<array<string, mixed>>
     */
    public function acompanantes(): array
    {
        return collect($this->validated('companions', []))
            ->filter(fn (array $companion): bool => filled($companion['document_number'] ?? null))
            ->values()
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'primary_guest' => $this->normalizarHuesped($this->input('primary_guest', [])),
            'companions' => array_map($this->normalizarHuesped(...), $this->input('companions', [])),
        ]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function reglasDeHuesped(string $prefix, string $requiredRule): array
    {
        return [
            "{$prefix}.document_type" => [$requiredRule, Rule::enum(DocumentType::class)],
            "{$prefix}.document_number" => [$prefix === 'primary_guest' ? 'required' : 'nullable', 'string', 'max:30'],
            "{$prefix}.document_complement" => ['nullable', 'string', 'max:10'],
            "{$prefix}.issuing_country" => [$requiredRule, 'string', 'size:2', 'alpha'],
            "{$prefix}.first_name" => [$requiredRule, 'string', 'max:100'],
            "{$prefix}.last_name" => [$requiredRule, 'string', 'max:100'],
            "{$prefix}.nationality" => ['nullable', 'string', 'size:2', 'alpha'],
            "{$prefix}.phone" => ['nullable', 'string', 'max:30'],
            "{$prefix}.email" => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $guest
     * @return array<string, mixed>
     */
    private function normalizarHuesped(array $guest): array
    {
        foreach (['issuing_country', 'nationality', 'document_number', 'document_complement'] as $field) {
            if (is_string($guest[$field] ?? null)) {
                $guest[$field] = mb_strtoupper(trim($guest[$field]));
            }
        }

        return $guest;
    }
}
