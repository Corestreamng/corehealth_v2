<?php

use Illuminate\Database\Migrations\Migration;

class ChangeAdmissionReasonToTextInAdmissionRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE admission_requests MODIFY admission_reason TEXT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE admission_requests MODIFY admission_reason VARCHAR(255) NULL');
    }
}
