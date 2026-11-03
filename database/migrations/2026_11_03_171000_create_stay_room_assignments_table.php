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
        Schema::create('stay_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->decimal('rate_amount', 12, 2);
            $table->timestamp('assigned_from');
            $table->timestamp('assigned_to')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['stay_id', 'assigned_from']);
        });

        DB::statement('ALTER TABLE stay_room_assignments ADD CONSTRAINT stay_room_assignments_rate_amount_positive CHECK (rate_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stay_room_assignments');
    }
};
