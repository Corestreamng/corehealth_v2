<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReasonColumnsToAdmissionRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->string('admission_reason')->nullable()->after('note');
            $table->string('discharge_reason')->nullable()->after('discharge_date');
            $table->text('discharge_note')->nullable()->after('discharge_reason');
            $table->text('followup_instructions')->nullable()->after('discharge_note');
            $table->string('priority')->default('routine')->after('bed_id'); // routine, urgent, emergency
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropColumn(['admission_reason', 'discharge_reason', 'discharge_note', 'followup_instructions', 'priority']);
        });
    }
}
