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
        Schema::table('stay_charges', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('total_amount')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->unique()->after('product_id')->constrained()->restrictOnDelete();
        });

        // A sale is a product charge that names the product and the movement that took it out of stock.
        // Product charges entered by hand before the inventory existed keep both columns empty.
        DB::statement(<<<'SQL'
            ALTER TABLE stay_charges ADD CONSTRAINT stay_charges_product_sale_complete CHECK (
                (product_id IS NULL AND inventory_movement_id IS NULL)
                OR (charge_type = 'product' AND product_id IS NOT NULL AND inventory_movement_id IS NOT NULL)
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE stay_charges DROP CHECK stay_charges_product_sale_complete');

        Schema::table('stay_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_movement_id');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
