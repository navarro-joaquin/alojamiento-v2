<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `primary_stay_id` allows at most one primary guest per stay.
     */
    public function up(): void
    {
        Schema::create('stay_guests', function (Blueprint $table) {
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('relationship', 30)->nullable();
            $table->unsignedBigInteger('primary_stay_id')
                ->nullable()
                ->storedAs('IF(is_primary, stay_id, NULL)');
            $table->timestamps();

            $table->primary(['stay_id', 'guest_id']);
            $table->unique('primary_stay_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stay_guests');
    }
};
