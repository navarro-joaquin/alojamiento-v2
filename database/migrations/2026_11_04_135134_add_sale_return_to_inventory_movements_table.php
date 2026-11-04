<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Voiding a product charge puts the stock back with a sale_return movement.
        DB::statement('ALTER TABLE inventory_movements DROP CHECK inventory_movements_direction_matches_type');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_direction_matches_type CHECK (
                (movement_type IN ('entry', 'transfer_in', 'sale_return') AND direction = 'in')
                OR (movement_type IN ('exit', 'sale', 'consumption', 'transfer_out') AND direction = 'out')
                OR (movement_type = 'adjustment' AND direction IN ('in', 'out'))
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_movements DROP CHECK inventory_movements_direction_matches_type');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_direction_matches_type CHECK (
                (movement_type IN ('entry', 'transfer_in') AND direction = 'in')
                OR (movement_type IN ('exit', 'sale', 'consumption', 'transfer_out') AND direction = 'out')
                OR (movement_type = 'adjustment' AND direction IN ('in', 'out'))
            )
            SQL);
    }
};
