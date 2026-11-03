<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The document number is stored encrypted; `document_number_hash` is a blind
     * index (HMAC of number + complement) used for lookups and uniqueness.
     */
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20);
            $table->text('document_number');
            $table->string('document_complement', 10)->nullable();
            $table->char('issuing_country', 2);
            $table->char('document_number_hash', 64);
            $table->string('first_name');
            $table->string('last_name');
            $table->char('nationality', 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'issuing_country', 'document_number_hash']);
            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
