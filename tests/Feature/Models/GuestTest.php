<?php

use App\Enums\DocumentType;
use App\Models\Guest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('stores the document number encrypted', function () {
    $guest = Guest::factory()->create(['document_number' => '1234567']);

    $storedDocumentNumber = DB::table('guests')->where('id', $guest->id)->value('document_number');

    expect($storedDocumentNumber)->not->toBe('1234567')
        ->and($guest->fresh()->document_number)->toBe('1234567');
});

it('finds a guest by the blind index of its document', function () {
    $guest = Guest::factory()->create(['document_number' => '1234567', 'document_complement' => '1A']);

    $foundGuest = Guest::query()
        ->where('document_number_hash', Guest::calcularHashDocumento(' 1234567 ', '1a'))
        ->first();

    expect($foundGuest?->is($guest))->toBeTrue();
});

it('updates the blind index when the document number changes', function () {
    $guest = Guest::factory()->create(['document_number' => '1234567']);

    $guest->update(['document_number' => '7654321']);

    expect($guest->document_number_hash)->toBe(Guest::calcularHashDocumento('7654321'));
});

it('rejects a second guest with the same document', function () {
    Guest::factory()->create(['document_number' => '1234567']);

    Guest::factory()->create(['document_number' => '1234567']);
})->throws(QueryException::class, 'Duplicate entry');

it('accepts the same document number with a different complement', function () {
    Guest::factory()->create(['document_number' => '1234567']);

    Guest::factory()->create(['document_number' => '1234567', 'document_complement' => '1A']);

    expect(Guest::query()->where('document_type', DocumentType::Ci)->count())->toBe(2);
});

it('hides the document number when serialized', function () {
    $guest = Guest::factory()->create();

    expect($guest->toArray())->not->toHaveKeys(['document_number', 'document_number_hash']);
});
