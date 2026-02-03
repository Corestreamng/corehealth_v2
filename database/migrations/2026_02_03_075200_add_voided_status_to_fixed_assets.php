<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add 'voided' status to fixed_assets status enum.
     * This allows assets to be voided (reversed) if registered in error.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('active', 'fully_depreciated', 'disposed', 'impaired', 'under_maintenance', 'idle', 'voided') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, update any voided assets to a valid status before removing the enum value
        DB::statement("UPDATE fixed_assets SET status = 'disposed' WHERE status = 'voided'");

        DB::statement("ALTER TABLE fixed_assets MODIFY COLUMN status ENUM('active', 'fully_depreciated', 'disposed', 'impaired', 'under_maintenance', 'idle') DEFAULT 'active'");
    }
};
