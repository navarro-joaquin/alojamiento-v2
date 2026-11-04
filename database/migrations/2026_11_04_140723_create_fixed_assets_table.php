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
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('asset_code', 50)->unique();
            $table->string('name');
            $table->string('serial_number', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        DB::statement('ALTER TABLE fixed_assets ADD CONSTRAINT fixed_assets_purchase_cost_not_negative CHECK (purchase_cost IS NULL OR purchase_cost >= 0)');
        // Only an asset in use sits in a room; in storage, in repair or retired it has none.
        DB::statement("ALTER TABLE fixed_assets ADD CONSTRAINT fixed_assets_room_only_in_use CHECK (room_id IS NULL OR status = 'in_use')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
