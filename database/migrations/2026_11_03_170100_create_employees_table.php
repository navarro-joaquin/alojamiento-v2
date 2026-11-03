<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('employee_code', 30);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'employee_code']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
