<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastBedBillingDateToApplicationStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'last_bed_billing_date')) {
                $table->date('last_bed_billing_date')->nullable()->after('bed_service_category_id');
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
            if (Schema::hasColumn('application_status', 'last_bed_billing_date')) {
                $table->dropColumn('last_bed_billing_date');
            }
        });
    }
}
