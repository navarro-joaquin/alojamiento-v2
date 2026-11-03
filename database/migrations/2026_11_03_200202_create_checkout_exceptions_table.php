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
     * `open_stay_id` guarantees at the database level that a stay has at most
     * one exception that is requested or approved.
     */
    public function up(): void
    {
        Schema::create('checkout_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('outstanding_amount', 12, 2);
            $table->text('reason');
            $table->text('resolution_notes')->nullable();
            $table->string('status', 20)->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('open_stay_id')
                ->nullable()
                ->storedAs("IF(status IN ('requested', 'approved'), stay_id, NULL)");
            $table->timestamps();

            $table->unique('open_stay_id');
            $table->index(['stay_id', 'status']);
            $table->index(['approved_by', 'approved_at']);
        });

        DB::statement('ALTER TABLE checkout_exceptions ADD CONSTRAINT checkout_exceptions_outstanding_amount_positive CHECK (outstanding_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_exceptions');
    }
};
