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
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id']);
        });

        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_quantity_not_negative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_average_cost_not_negative CHECK (average_cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
