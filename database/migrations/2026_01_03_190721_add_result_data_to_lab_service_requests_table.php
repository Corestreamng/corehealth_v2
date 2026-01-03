<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResultDataToLabServiceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->json('result_data')->nullable()->after('result')->comment('Structured result data for V2 templates');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropColumn('result_data');
        });
    }
}
