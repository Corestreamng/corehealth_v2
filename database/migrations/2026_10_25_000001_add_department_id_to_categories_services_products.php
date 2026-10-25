<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDepartmentIdToCategoriesServicesProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. service_categories
        if (Schema::hasTable('service_categories') && !Schema::hasColumn('service_categories', 'department_id')) {
            Schema::table('service_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('status');
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }

        // 2. product_categories
        if (Schema::hasTable('product_categories') && !Schema::hasColumn('product_categories', 'department_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('status');
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }

        // 3. services
        if (Schema::hasTable('services') && !Schema::hasColumn('services', 'department_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('category_id');
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }

        // 4. products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'department_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('category_id');
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }

        // 5. product_or_service_requests (denormalized snapshot for fast query/reporting)
        if (Schema::hasTable('product_or_service_requests') && !Schema::hasColumn('product_or_service_requests', 'department_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('service_id');
                $table->index('department_id', 'posr_department_id_index');
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('product_or_service_requests') && Schema::hasColumn('product_or_service_requests', 'department_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                }
                $table->dropIndex('posr_department_id_index');
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'department_id')) {
            Schema::table('products', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'department_id')) {
            Schema::table('services', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('product_categories') && Schema::hasColumn('product_categories', 'department_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('department_id');
            });
        }

        if (Schema::hasTable('service_categories') && Schema::hasColumn('service_categories', 'department_id')) {
            Schema::table('service_categories', function (Blueprint $table) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('department_id');
            });
        }
    }
}
