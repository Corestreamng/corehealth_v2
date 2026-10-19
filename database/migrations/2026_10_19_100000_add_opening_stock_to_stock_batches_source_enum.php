<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_batches MODIFY COLUMN source ENUM('purchase_order','manual','transfer_in','opening_stock') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_batches MODIFY COLUMN source ENUM('purchase_order','manual','transfer_in') NOT NULL DEFAULT 'manual'");
    }
};
