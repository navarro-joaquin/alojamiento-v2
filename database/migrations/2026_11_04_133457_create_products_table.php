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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('unit', 30);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->boolean('is_sellable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A product sold to guests needs its price; the price, when present, is never zero or negative.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sale_price_positive CHECK (sale_price IS NULL OR sale_price > 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sellable_has_price CHECK (is_sellable = 0 OR sale_price IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
