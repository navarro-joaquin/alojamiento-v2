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
     * A folio is the account that charges are billed to and payments are
     * applied to. Every stay has exactly one folio of its own; other folio
     * types have no stay.
     */
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('folio_type', 20);
            $table->foreignId('stay_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('holder_name')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'folio_type']);
        });

        DB::statement("ALTER TABLE folios ADD CONSTRAINT folios_stay_only_for_stay_type CHECK ((folio_type = 'stay') = (stay_id IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
