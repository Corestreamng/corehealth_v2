<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['drug', 'consumable', 'utility'])
                  ->default('drug')
                  ->after('category_id');

            $table->string('base_unit_name', 50)
                  ->default('Piece')
                  ->after('product_type');

            $table->boolean('allow_decimal_qty')
                  ->default(false)
                  ->after('base_unit_name');

            $table->index('product_type', 'idx_products_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_type');
            $table->dropColumn(['product_type', 'base_unit_name', 'allow_decimal_qty']);
        });
    }
};
