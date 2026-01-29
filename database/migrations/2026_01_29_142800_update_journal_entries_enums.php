<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Update journal_entries enum columns to include additional values.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update status enum to include 'rejected'
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'posted', 'reversed', 'rejected') DEFAULT 'draft'");

        // Update entry_type enum to include 'adjustment'
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN entry_type ENUM('auto', 'manual', 'opening', 'closing', 'reversal', 'adjustment')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enums
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'posted', 'reversed') DEFAULT 'draft'");
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN entry_type ENUM('auto', 'manual', 'opening', 'closing', 'reversal')");
    }
};
