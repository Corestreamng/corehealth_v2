<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSelfPerformIntentToLabImagingRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->boolean('self_perform_intent')->nullable()->default(null)->after('billed_date');
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->boolean('self_perform_intent')->nullable()->default(null)->after('billed_date');
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
            $table->dropColumn('self_perform_intent');
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropColumn('self_perform_intent');
        });
    }
}
