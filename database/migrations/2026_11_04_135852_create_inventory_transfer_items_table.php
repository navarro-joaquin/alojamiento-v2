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
        Schema::create('inventory_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('received_quantity', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['inventory_transfer_id', 'product_id']);
        });

        DB::statement('ALTER TABLE inventory_transfer_items ADD CONSTRAINT inventory_transfer_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE inventory_transfer_items ADD CONSTRAINT inventory_transfer_items_received_within_sent CHECK (received_quantity IS NULL OR (received_quantity >= 0 AND received_quantity <= quantity))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
    }
};
