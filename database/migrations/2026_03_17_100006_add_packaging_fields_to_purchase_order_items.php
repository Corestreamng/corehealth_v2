<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('packaging_id')->nullable()->after('product_id')
                  ->constrained('product_packagings')->nullOnDelete();
            $table->decimal('packaging_qty', 12, 4)->nullable()->after('packaging_id');

            $table->foreignId('received_packaging_id')->nullable()->after('received_qty')
                  ->constrained('product_packagings')->nullOnDelete();
            $table->decimal('received_packaging_qty', 12, 4)->nullable()->after('received_packaging_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['packaging_id']);
            $table->dropForeign(['received_packaging_id']);
            $table->dropColumn([
                'packaging_id',
                'packaging_qty',
                'received_packaging_id',
                'received_packaging_qty',
            ]);
        });
    }
};
