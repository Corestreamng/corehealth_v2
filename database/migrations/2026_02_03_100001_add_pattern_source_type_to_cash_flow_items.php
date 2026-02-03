<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add 'pattern' source type to cash_flow_forecast_items
 *
 * This allows tracking items that were auto-generated from
 * recurring cash flow patterns via the observer.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'pattern' to the source_type enum
        DB::statement("ALTER TABLE cash_flow_forecast_items MODIFY COLUMN source_type ENUM('manual', 'recurring', 'pattern', 'scheduled', 'historical', 'commitment') DEFAULT 'manual'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values (convert any 'pattern' to 'recurring' first)
        DB::statement("UPDATE cash_flow_forecast_items SET source_type = 'recurring' WHERE source_type = 'pattern'");
        DB::statement("ALTER TABLE cash_flow_forecast_items MODIFY COLUMN source_type ENUM('manual', 'recurring', 'scheduled', 'historical', 'commitment') DEFAULT 'manual'");
    }
};
