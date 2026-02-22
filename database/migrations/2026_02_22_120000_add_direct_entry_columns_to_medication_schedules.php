<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Make product_or_service_request_id nullable (direct entries have no POSR)
        // Drop FK first, modify, re-add FK
        try {
            Schema::table('medication_schedules', function ($table) {
                $table->dropForeign(['product_or_service_request_id']);
            });
        } catch (\Exception $e) {
            // FK may not exist
        }

        DB::statement('ALTER TABLE medication_schedules MODIFY product_or_service_request_id BIGINT UNSIGNED NULL');

        Schema::table('medication_schedules', function ($table) {
            $table->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests')
                  ->nullOnDelete();
        });

        // Add new columns for direct entry scheduling
        if (!Schema::hasColumn('medication_schedules', 'product_id')) {
            Schema::table('medication_schedules', function ($table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('product_or_service_request_id');
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('medication_schedules', 'drug_source')) {
            Schema::table('medication_schedules', function ($table) {
                $table->string('drug_source', 30)->default('pharmacy_dispensed')->after('product_id');
            });
        }

        if (!Schema::hasColumn('medication_schedules', 'external_drug_name')) {
            Schema::table('medication_schedules', function ($table) {
                $table->string('external_drug_name', 255)->nullable()->after('drug_source');
            });
        }
    }

    public function down()
    {
        Schema::table('medication_schedules', function ($table) {
            if (Schema::hasColumn('medication_schedules', 'external_drug_name')) {
                $table->dropColumn('external_drug_name');
            }
            if (Schema::hasColumn('medication_schedules', 'drug_source')) {
                $table->dropColumn('drug_source');
            }
            if (Schema::hasColumn('medication_schedules', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });

        // Revert nullable
        try {
            Schema::table('medication_schedules', function ($table) {
                $table->dropForeign(['product_or_service_request_id']);
            });
        } catch (\Exception $e) {}

        DB::statement('ALTER TABLE medication_schedules MODIFY product_or_service_request_id BIGINT UNSIGNED NOT NULL');

        Schema::table('medication_schedules', function ($table) {
            $table->foreign('product_or_service_request_id')
                  ->references('id')->on('product_or_service_requests');
        });
    }
};
