<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `denominations` holds the number of bills and coins counted, keyed by
     * their value in centavos.
     */
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->string('count_type', 20);
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('declared_amount', 12, 2);
            $table->decimal('difference_amount', 12, 2);
            $table->json('denominations')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();

            $table->index(['cash_session_id', 'counted_at']);
        });

        DB::statement('ALTER TABLE cash_counts ADD CONSTRAINT cash_counts_declared_amount_non_negative CHECK (declared_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
