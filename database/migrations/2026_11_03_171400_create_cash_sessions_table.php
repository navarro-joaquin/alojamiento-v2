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
     * `open_register_id` guarantees at the database level that a cash register
     * has at most one session that is open or being closed.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('business_date');
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('declared_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('open_register_id')
                ->nullable()
                ->storedAs("IF(status IN ('open', 'closing'), cash_register_id, NULL)");
            $table->timestamps();

            $table->unique('open_register_id');
            $table->index(['cash_register_id', 'status']);
            $table->index(['opened_by', 'opened_at']);
        });

        DB::statement('ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_opening_amount_non_negative CHECK (opening_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
