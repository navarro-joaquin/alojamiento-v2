<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('field', 30);
            $table->string('old_value', 20)->nullable();
            $table->string('new_value', 20);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['room_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_status_logs');
    }
};
