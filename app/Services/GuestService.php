<?php

namespace App\Services;

use App\Models\Guest;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Finds guests by their identity document, which is stored encrypted and
 * searched through its blind index.
 */
class GuestService
{
    /**
     * Returns the guest holding the document, creating it if needed. The
     * names and contact details typed at the front desk replace the stored ones.
     *
     * @param  array{document_type: string, document_number: string, document_complement?: ?string, issuing_country: string, first_name: string, last_name: string}&array<string, mixed>  $attributes
     */
    public function identificar(array $attributes): Guest
    {
        $guest = $this->buscarPorDocumento($attributes) ?? new Guest;
        $guest->fill($attributes);

        try {
            $guest->save();
        } catch (UniqueConstraintViolationException $exception) {
            $guest = $this->buscarPorDocumento($attributes) ?? throw $exception;
            $guest->update($attributes);
        }

        return $guest;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function buscarPorDocumento(array $attributes): ?Guest
    {
        return Guest::query()
            ->where('document_type', $attributes['document_type'])
            ->where('issuing_country', $attributes['issuing_country'])
            ->where('document_number_hash', Guest::calcularHashDocumento($attributes['document_number'], $attributes['document_complement'] ?? null))
            ->first();
    }
}
