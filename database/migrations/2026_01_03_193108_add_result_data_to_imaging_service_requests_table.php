<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResultDataToImagingServiceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->json('result_data')->nullable()->after('result');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropColumn('result_data');
        });
    }
}
