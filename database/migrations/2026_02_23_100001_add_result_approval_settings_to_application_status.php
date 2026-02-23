<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResultApprovalSettingsToApplicationStatus extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'lab_results_require_approval')) {
                $table->boolean('lab_results_require_approval')->default(false)->after('enable_twakto');
            }
            if (!Schema::hasColumn('application_status', 'imaging_results_require_approval')) {
                $table->boolean('imaging_results_require_approval')->default(false)->after('lab_results_require_approval');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (Schema::hasColumn('application_status', 'lab_results_require_approval')) {
                $table->dropColumn('lab_results_require_approval');
            }
            if (Schema::hasColumn('application_status', 'imaging_results_require_approval')) {
                $table->dropColumn('imaging_results_require_approval');
            }
        });
    }
}
