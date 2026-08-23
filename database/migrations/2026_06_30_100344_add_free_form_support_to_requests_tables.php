<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFreeFormSupportToRequestsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. product_requests
        DB::statement("UPDATE product_requests SET product_id = '0' WHERE product_id = ''");
        DB::statement('ALTER TABLE product_requests MODIFY product_id BIGINT UNSIGNED NULL');
        DB::statement("UPDATE product_requests SET product_id = NULL WHERE product_id = '0'");
        if (!Schema::hasColumn('product_requests', 'is_free_form')) {
            Schema::table('product_requests', function (Blueprint $table) {
                $table->boolean('is_free_form')->default(false)->after('product_id');
                $table->string('free_form_name')->nullable()->after('is_free_form');
            });
        }

        // 2. lab_service_requests
        DB::statement("UPDATE lab_service_requests SET service_id = '0' WHERE service_id = ''");
        DB::statement('ALTER TABLE lab_service_requests MODIFY service_id BIGINT UNSIGNED NULL');
        DB::statement("UPDATE lab_service_requests SET service_id = NULL WHERE service_id = '0'");
        if (!Schema::hasColumn('lab_service_requests', 'is_free_form')) {
            Schema::table('lab_service_requests', function (Blueprint $table) {
                $table->boolean('is_free_form')->default(false)->after('service_id');
                $table->string('free_form_name')->nullable()->after('is_free_form');
            });
        }

        // 3. imaging_service_requests
        DB::statement("UPDATE imaging_service_requests SET service_id = '0' WHERE service_id = ''");
        DB::statement('ALTER TABLE imaging_service_requests MODIFY service_id BIGINT UNSIGNED NULL');
        DB::statement("UPDATE imaging_service_requests SET service_id = NULL WHERE service_id = '0'");
        if (!Schema::hasColumn('imaging_service_requests', 'is_free_form')) {
            Schema::table('imaging_service_requests', function (Blueprint $table) {
                $table->boolean('is_free_form')->default(false)->after('service_id');
                $table->string('free_form_name')->nullable()->after('is_free_form');
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
        Schema::table('requests_tables', function (Blueprint $table) {
            //
        });
    }
}
