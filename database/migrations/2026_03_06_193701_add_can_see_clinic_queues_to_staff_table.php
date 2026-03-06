<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCanSeeClinicQueuesToStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            // JSON array of extra clinic IDs whose queues this doctor can monitor
            // e.g. [2, 5, 8] — in addition to their primary clinic_id
            $table->json('can_see_clinic_queues')->nullable()->after('clinic_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('can_see_clinic_queues');
        });
    }
}
