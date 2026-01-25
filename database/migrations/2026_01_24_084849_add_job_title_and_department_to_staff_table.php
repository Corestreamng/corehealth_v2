<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJobTitleAndDepartmentToStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'job_title')) {
                $table->string('job_title')->nullable()->after('employment_status');
            }
            if (!Schema::hasColumn('staff', 'department')) {
                $table->string('department')->nullable()->after('job_title');
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
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'department']);
        });
    }
}
