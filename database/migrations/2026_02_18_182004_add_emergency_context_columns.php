<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmergencyContextColumns extends Migration
{
    public function up()
    {
        // 1. DoctorQueue: priority, source, triage_note
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->string('priority', 20)->default('routine')->after('status')
                  ->comment('routine|urgent|emergency');
            $table->string('source', 30)->default('reception')->after('priority')
                  ->comment('reception|emergency_intake|appointment');
            $table->text('triage_note')->nullable()->after('source')
                  ->comment('Triage narrative for queue_consultation path');
        });

        // 2. LabServiceRequest: priority
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('routine')->after('status')
                  ->comment('routine|urgent|emergency');
        });

        // 3. ImagingServiceRequest: priority
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default('routine')->after('status')
                  ->comment('routine|urgent|emergency');
        });

        // 4. VitalSign: source
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('status')
                  ->comment('emergency_intake|nursing|doctor');
        });

        // 5. AdmissionRequest: esi_level, chief_complaint (structured)
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->tinyInteger('esi_level')->nullable()->after('priority')
                  ->comment('ESI triage level 1-5');
            $table->text('chief_complaint')->nullable()->after('esi_level');
        });
    }

    public function down()
    {
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->dropColumn(['priority', 'source', 'triage_note']);
        });
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropColumn(['esi_level', 'chief_complaint']);
        });
    }
}
