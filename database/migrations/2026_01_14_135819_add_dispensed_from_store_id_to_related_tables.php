<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add dispensed_from_store_id to track which store items are dispensed from.
 * This enables proper stock tracking per store location.
 *
 * Tables affected:
 * - product_requests: For pharmacy prescriptions
 * - injection_administrations: For nursing injections
 * - immunization_records: For nursing immunizations
 * - product_or_service_requests: For consumables and direct bills
 */
class AddDispensedFromStoreIdToRelatedTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add to product_requests (pharmacy prescriptions)
        if (Schema::hasTable('product_requests') && !Schema::hasColumn('product_requests', 'dispensed_from_store_id')) {
            Schema::table('product_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('dispensed_from_store_id')->nullable()->after('dispense_date');
                $table->foreign('dispensed_from_store_id')->references('id')->on('stores')->nullOnDelete();
                $table->integer('qty')->default(1)->after('dose');
            });
        }

        // Add to injection_administrations (nursing injections)
        if (Schema::hasTable('injection_administrations') && !Schema::hasColumn('injection_administrations', 'dispensed_from_store_id')) {
            Schema::table('injection_administrations', function (Blueprint $table) {
                $table->unsignedBigInteger('dispensed_from_store_id')->nullable()->after('administered_by');
                $table->foreign('dispensed_from_store_id')->references('id')->on('stores')->nullOnDelete();
            });
        }

        // Add to immunization_records (nursing immunizations)
        if (Schema::hasTable('immunization_records') && !Schema::hasColumn('immunization_records', 'dispensed_from_store_id')) {
            Schema::table('immunization_records', function (Blueprint $table) {
                $table->unsignedBigInteger('dispensed_from_store_id')->nullable()->after('administered_by');
                $table->foreign('dispensed_from_store_id')->references('id')->on('stores')->nullOnDelete();
            });
        }

        // Add to product_or_service_requests (for consumables tracking)
        if (Schema::hasTable('product_or_service_requests') && !Schema::hasColumn('product_or_service_requests', 'dispensed_from_store_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('dispensed_from_store_id')->nullable()->after('staff_user_id');
                $table->foreign('dispensed_from_store_id')->references('id')->on('stores')->nullOnDelete();
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
        if (Schema::hasColumn('product_requests', 'dispensed_from_store_id')) {
            Schema::table('product_requests', function (Blueprint $table) {
                $table->dropForeign(['dispensed_from_store_id']);
                $table->dropColumn('dispensed_from_store_id');
            });
        }

        if (Schema::hasColumn('injection_administrations', 'dispensed_from_store_id')) {
            Schema::table('injection_administrations', function (Blueprint $table) {
                $table->dropForeign(['dispensed_from_store_id']);
                $table->dropColumn('dispensed_from_store_id');
            });
        }

        if (Schema::hasColumn('immunization_records', 'dispensed_from_store_id')) {
            Schema::table('immunization_records', function (Blueprint $table) {
                $table->dropForeign(['dispensed_from_store_id']);
                $table->dropColumn('dispensed_from_store_id');
            });
        }

        if (Schema::hasColumn('product_or_service_requests', 'dispensed_from_store_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                $table->dropForeign(['dispensed_from_store_id']);
                $table->dropColumn('dispensed_from_store_id');
            });
        }
    }
}
