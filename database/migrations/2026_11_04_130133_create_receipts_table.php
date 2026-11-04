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
     * `issued_payment_id` guarantees at the database level that a payment has
     * at most one receipt in force; a voided receipt can be replaced.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('prefix', 20);
            $table->unsignedInteger('number');
            $table->string('holder_name');
            $table->text('holder_document')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('issued');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('issued_payment_id')
                ->nullable()
                ->storedAs("IF(status = 'issued', payment_id, NULL)");
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->unique('issued_payment_id');
            $table->index(['branch_id', 'issued_at']);
        });

        DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
