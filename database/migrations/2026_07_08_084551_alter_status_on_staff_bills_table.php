<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterStatusOnStaffBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE staff_bills MODIFY status ENUM('pending_audit', 'pending', 'paid', 'rejected') DEFAULT 'pending_audit'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Careful with down if data already has 'pending_audit'
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE staff_bills MODIFY status ENUM('pending', 'paid') DEFAULT 'pending'");
    }
}
