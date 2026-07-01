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
        DB::statement('ALTER TABLE product_requests MODIFY product_id BIGINT UNSIGNED NULL');
        Schema::table('product_requests', function (Blueprint $table) {
            $table->boolean('is_free_form')->default(false)->after('product_id');
            $table->string('free_form_name')->nullable()->after('is_free_form');
        });

        // 2. lab_service_requests
        DB::statement('ALTER TABLE lab_service_requests MODIFY service_id BIGINT UNSIGNED NULL');
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->boolean('is_free_form')->default(false)->after('service_id');
            $table->string('free_form_name')->nullable()->after('is_free_form');
        });

        // 3. imaging_service_requests
        DB::statement('ALTER TABLE imaging_service_requests MODIFY service_id BIGINT UNSIGNED NULL');
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->boolean('is_free_form')->default(false)->after('service_id');
            $table->string('free_form_name')->nullable()->after('is_free_form');
        });
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
