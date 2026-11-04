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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->string('direction', 10);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->uuid('operation_uuid')->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id', 'id']);
        });

        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_unit_cost_not_negative CHECK (unit_cost >= 0)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_balance_after_not_negative CHECK (balance_after >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_direction_matches_type CHECK (
                (movement_type IN ('entry', 'transfer_in') AND direction = 'in')
                OR (movement_type IN ('exit', 'sale', 'consumption', 'transfer_out') AND direction = 'out')
                OR (movement_type = 'adjustment' AND direction IN ('in', 'out'))
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
