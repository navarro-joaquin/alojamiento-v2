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
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')
                ->nullable()
                ->virtualAs("CAST(JSON_UNQUOTE(JSON_EXTRACT(properties, '$.branch_id')) AS UNSIGNED)");

            $table->index(['branch_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropColumn('branch_id');
        });
    }
};
