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
     * `active_room_id` guarantees at the database level that a room has at most
     * one active stay, even if a service skips the row lock.
     */
    public function up(): void
    {
        Schema::create('stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('check_in_at');
            $table->timestamp('expected_check_out_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('rate_amount', 12, 2);
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('active_room_id')
                ->nullable()
                ->storedAs("IF(status = 'active', room_id, NULL)");
            $table->timestamps();

            $table->unique('active_room_id');
            $table->index(['branch_id', 'status']);
        });

        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_rate_amount_positive CHECK (rate_amount > 0)');
        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_adults_positive CHECK (adults >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stays');
    }
};
