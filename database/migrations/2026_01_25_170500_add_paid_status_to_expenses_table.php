<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'paid' status
        DB::statement("ALTER TABLE `expenses` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'void', 'paid') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any 'paid' records back to 'approved'
        DB::table('expenses')->where('status', 'paid')->update(['status' => 'approved']);
        
        // Then revert the enum
        DB::statement("ALTER TABLE `expenses` MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'void') NOT NULL DEFAULT 'pending'");
    }
};
