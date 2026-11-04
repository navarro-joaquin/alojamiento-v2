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
     * Opens the folio of every existing stay and bills its charges, payments
     * and receipts to it. Charges keep `stay_id` as the stay they come from;
     * payments and receipts only belong to the folio.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO folios (branch_id, folio_type, stay_id, created_by, created_at, updated_at)
            SELECT branch_id, 'stay', id, created_by, created_at, created_at FROM stays ORDER BY id
            SQL);

        foreach (['stay_charges', 'payments', 'receipts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('folio_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });

            DB::statement("UPDATE {$tableName} JOIN folios ON folios.stay_id = {$tableName}.stay_id SET {$tableName}.folio_id = folios.id");

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('folio_id')->nullable(false)->change();
            });
        }

        Schema::table('stay_charges', function (Blueprint $table) {
            $table->index(['folio_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['stay_id']);
            $table->dropIndex(['stay_id', 'status']);
            $table->dropColumn('stay_id');
            $table->index(['folio_id', 'status']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stay_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['payments', 'receipts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('stay_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });

            DB::statement("UPDATE {$tableName} JOIN folios ON folios.id = {$tableName}.folio_id SET {$tableName}.stay_id = folios.stay_id");

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('stay_id')->nullable(false)->change();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['stay_id', 'status']);
        });

        foreach (['stay_charges', 'payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['folio_id']);
                $table->dropIndex(['folio_id', 'status']);
                $table->dropColumn('folio_id');
            });
        }

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folio_id');
        });

        DB::table('folios')->delete();
    }
};
