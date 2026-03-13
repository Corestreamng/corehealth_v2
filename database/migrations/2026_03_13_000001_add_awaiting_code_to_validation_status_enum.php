<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE product_or_service_requests MODIFY COLUMN validation_status ENUM('pending', 'approved', 'rejected', 'awaiting_code') NULL COMMENT 'HMO validation status'");
    }

    public function down(): void
    {
        // Move any awaiting_code back to pending before shrinking enum
        DB::table('product_or_service_requests')
            ->where('validation_status', 'awaiting_code')
            ->update(['validation_status' => 'pending']);

        DB::statement("ALTER TABLE product_or_service_requests MODIFY COLUMN validation_status ENUM('pending', 'approved', 'rejected') NULL COMMENT 'HMO validation status'");
    }
};
