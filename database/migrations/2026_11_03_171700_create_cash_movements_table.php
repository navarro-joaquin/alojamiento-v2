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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->string('payment_method', 20)->nullable();
            $table->string('direction', 10);
            $table->decimal('amount', 12, 2);
            $table->morphs('source');
            $table->boolean('affects_cash')->default(true);
            $table->uuid('operation_uuid')->unique();
            $table->string('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['cash_session_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_valid CHECK (direction IN ('in', 'out'))");
        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
