<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('refund_method', 20);
            $table->string('status', 20)->default('confirmed');
            $table->string('reference')->nullable();
            $table->string('reason');
            $table->uuid('operation_uuid')->unique();
            $table->char('request_hash', 64);
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['cash_session_id', 'refunded_at']);
        });

        DB::statement('ALTER TABLE payment_refunds ADD CONSTRAINT payment_refunds_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
