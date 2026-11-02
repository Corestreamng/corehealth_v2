<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandStaffEmploymentStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE staff MODIFY COLUMN employment_status ENUM('active', 'probation', 'on_leave', 'suspended', 'resigned', 'terminated', 'retired') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE staff MODIFY COLUMN employment_status ENUM('active', 'suspended', 'terminated', 'resigned') NOT NULL DEFAULT 'active'");
    }
}
