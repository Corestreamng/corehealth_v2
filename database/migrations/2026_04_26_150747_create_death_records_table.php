<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeathRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('death_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters');
            $table->foreignId('admission_request_id')->nullable()->constrained('admission_requests');
            $table->enum('death_type', ['RIP', 'BID'])->default('RIP');
            $table->date('date_of_death');
            $table->time('time_of_death')->nullable();
            $table->string('cause_of_death_primary')->nullable(); // ICD-10 Code
            $table->text('cause_of_death_description')->nullable();
            $table->foreignId('certified_by_doctor_id')->nullable()->constrained('users');
            
            // Last Office (Nursing)
            $table->boolean('last_office_done')->default(false);
            $table->foreignId('last_office_by_nurse_id')->nullable()->constrained('users');
            $table->timestamp('last_office_at')->nullable();
            
            // Disposition
            $table->enum('disposition', ['pending', 'morgue', 'family_release'])->default('pending');
            $table->text('disposition_note')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('death_records');
    }
}
