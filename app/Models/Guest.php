<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'document_type',
    'document_number',
    'document_complement',
    'issuing_country',
    'first_name',
    'last_name',
    'nationality',
    'birth_date',
    'sex',
    'address',
    'city',
    'country',
    'phone',
    'email',
])]
#[Hidden(['document_number', 'document_number_hash'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    /**
     * Keep the blind index in sync with the encrypted document number.
     */
    protected static function booted(): void
    {
        static::saving(function (Guest $guest): void {
            $guest->document_number_hash = static::calcularHashDocumento(
                $guest->document_number,
                $guest->document_complement,
            );
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'document_number' => 'encrypted',
            'birth_date' => 'date',
        ];
    }

    /**
     * Blind index used to look up and deduplicate guests without decrypting
     * every document number. Depends on APP_KEY: rotating the key requires
     * recomputing every hash.
     */
    public static function calcularHashDocumento(string $documentNumber, ?string $documentComplement = null): string
    {
        $normalizedDocument = mb_strtoupper(trim($documentNumber)).'|'.mb_strtoupper(trim($documentComplement ?? ''));

        return hash_hmac('sha256', $normalizedDocument, (string) config('app.key'));
    }

    /**
     * @return BelongsToMany<Stay, $this>
     */
    public function stays(): BelongsToMany
    {
        return $this->belongsToMany(Stay::class, 'stay_guests')
            ->withPivot(['is_primary', 'relationship'])
            ->withTimestamps();
    }
}
