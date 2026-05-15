<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSlowQuerySettingsToApplicationStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->string('slow_query_log_path')->nullable();
            $table->unsignedBigInteger('slow_query_log_offset')->default(0);
            $table->dateTime('last_slow_query_check')->nullable();
        });
    }

    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn(['slow_query_log_path', 'slow_query_log_offset', 'last_slow_query_check']);
        });
    }
}
