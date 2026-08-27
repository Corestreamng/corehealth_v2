<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmergencyIntakeTogglesToApplicationStatusTable extends Migration
{
    public function up()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->boolean('enable_ei_reception')->default(1)->after('id');
            $table->boolean('enable_ei_imaging')->default(1)->after('enable_ei_reception');
            $table->boolean('enable_ei_hmo')->default(1)->after('enable_ei_imaging');
            $table->boolean('enable_ei_pharmacy')->default(1)->after('enable_ei_hmo');
            $table->boolean('enable_ei_nursing')->default(1)->after('enable_ei_pharmacy');
            $table->boolean('enable_ei_billing')->default(1)->after('enable_ei_nursing');
            $table->boolean('enable_ei_lab')->default(1)->after('enable_ei_billing');
            $table->boolean('enable_ei_doctor')->default(1)->after('enable_ei_lab');
        });
    }

    public function down()
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn([
                'enable_ei_reception',
                'enable_ei_imaging',
                'enable_ei_hmo',
                'enable_ei_pharmacy',
                'enable_ei_nursing',
                'enable_ei_billing',
                'enable_ei_lab',
                'enable_ei_doctor',
            ]);
        });
    }
}
