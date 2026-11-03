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
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('occupancy')->nullable();
            $table->json('days_of_week')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'room_type_id', 'is_active', 'valid_from']);
        });

        DB::statement('ALTER TABLE room_rates ADD CONSTRAINT room_rates_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE room_rates ADD CONSTRAINT room_rates_valid_range CHECK (valid_to IS NULL OR valid_to >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
