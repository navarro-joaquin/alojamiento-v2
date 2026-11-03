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
     * Amounts are always positive; `direction` gives the sign. `lodging_night_date`
     * allows a single confirmed lodging charge per stay and business date, which
     * makes the night audit idempotent.
     */
    public function up(): void
    {
        Schema::create('stay_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->string('charge_type', 30);
            $table->string('direction', 10)->default('debit');
            $table->string('description');
            $table->date('service_date');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('confirmed');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->date('lodging_night_date')
                ->nullable()
                ->storedAs("IF(charge_type = 'lodging' AND status = 'confirmed', service_date, NULL)");
            $table->timestamps();

            $table->unique(['stay_id', 'lodging_night_date']);
            $table->index(['stay_id', 'status']);
        });

        DB::statement("ALTER TABLE stay_charges ADD CONSTRAINT stay_charges_direction_valid CHECK (direction IN ('debit', 'credit'))");
        DB::statement('ALTER TABLE stay_charges ADD CONSTRAINT stay_charges_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE stay_charges ADD CONSTRAINT stay_charges_amounts_non_negative CHECK (unit_price >= 0 AND total_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stay_charges');
    }
};
