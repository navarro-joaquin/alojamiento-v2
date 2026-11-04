<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A counter sale is a folio without a stay, sold and paid in one
     * operation (`operation_uuid` makes a resubmission harmless) and voided
     * as a whole. Its charges come from no stay.
     */
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->uuid('operation_uuid')->nullable()->unique()->after('created_by');
            $table->char('request_hash', 64)->nullable()->after('operation_uuid');
            $table->foreignId('voided_by')->nullable()->after('request_hash')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->string('void_reason')->nullable()->after('voided_at');
        });

        Schema::table('stay_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('stay_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stay_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('stay_id')->nullable(false)->change();
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropUnique(['operation_uuid']);
            $table->dropColumn(['operation_uuid', 'request_hash', 'voided_at', 'void_reason']);
        });
    }
};
