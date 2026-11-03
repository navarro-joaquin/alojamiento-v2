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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('payment_method', 20);
            $table->string('payment_type', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->string('external_invoice_number', 50)->nullable();
            $table->uuid('operation_uuid')->unique();
            $table->char('request_hash', 64);
            $table->timestamp('paid_at');
            $table->string('status', 20)->default('confirmed');
            $table->timestamps();

            $table->index(['stay_id', 'status']);
            $table->index(['cash_session_id', 'paid_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
