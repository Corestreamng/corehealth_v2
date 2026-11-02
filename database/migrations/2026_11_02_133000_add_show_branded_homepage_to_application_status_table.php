<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'show_branded_homepage')) {
                $table->boolean('show_branded_homepage')->default(1)->after('site_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (Schema::hasColumn('application_status', 'show_branded_homepage')) {
                $table->dropColumn('show_branded_homepage');
            }
        });
    }
};
