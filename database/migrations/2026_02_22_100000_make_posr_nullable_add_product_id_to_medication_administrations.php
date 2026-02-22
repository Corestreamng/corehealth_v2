<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakePosrNullableAddProductIdToMedicationAdministrations extends Migration
{
    /**
     * §5.1: Direct administrations (patient_own, unbilled ward_stock) have no POSR linkage,
     * so product_or_service_request_id must be nullable. Also adds product_id for direct
     * ward stock entries where the product can't be derived from POSR/ProductRequest chain.
     */
    public function up()
    {
        // Drop foreign key first, then alter column to nullable via raw SQL (avoids Doctrine DBAL)
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['product_or_service_request_id']);
        });

        DB::statement('ALTER TABLE medication_administrations MODIFY product_or_service_request_id BIGINT UNSIGNED NULL');

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests')
                  ->onDelete('set null');
        });

        // Add product_id for direct ward stock entries
        if (!Schema::hasColumn('medication_administrations', 'product_id')) {
            Schema::table('medication_administrations', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('patient_id');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            if (Schema::hasColumn('medication_administrations', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['product_or_service_request_id']);
        });

        DB::statement('ALTER TABLE medication_administrations MODIFY product_or_service_request_id BIGINT UNSIGNED NOT NULL');

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests');
        });
    }
}
