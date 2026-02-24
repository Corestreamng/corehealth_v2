<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSearchIndexesToServicesProductsPrices extends Migration
{
    /**
     * Add indexes to speed up service/product search in billing tabs.
     */
    public function up()
    {
        // Services: searched by name/code with status filter
        Schema::table('services', function (Blueprint $table) {
            $table->index(['status', 'service_name'], 'idx_services_status_name');
            $table->index('service_code', 'idx_services_code');
            $table->index('category_id', 'idx_services_category');
        });

        // Products: searched by name/code with status filter
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'product_name'], 'idx_products_status_name');
            $table->index('product_code', 'idx_products_code');
            $table->index('category_id', 'idx_products_category');
        });

        // Service prices: joined via service_id
        Schema::table('service_prices', function (Blueprint $table) {
            $table->index('service_id', 'idx_service_prices_service_id');
        });

        // Product prices: joined via product_id
        Schema::table('prices', function (Blueprint $table) {
            $table->index('product_id', 'idx_prices_product_id');
        });

        // Stocks: joined via product_id
        Schema::table('stocks', function (Blueprint $table) {
            $table->index('product_id', 'idx_stocks_product_id');
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_status_name');
            $table->dropIndex('idx_services_code');
            $table->dropIndex('idx_services_category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_status_name');
            $table->dropIndex('idx_products_code');
            $table->dropIndex('idx_products_category');
        });

        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropIndex('idx_service_prices_service_id');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropIndex('idx_prices_product_id');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('idx_stocks_product_id');
        });
    }
}
