<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMorgueAdmissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('morgue_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('death_record_id')->constrained('death_records');
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('body_code')->unique();
            $table->string('fridge_number')->nullable();
            $table->string('tray_number')->nullable();
            $table->foreignId('daily_service_id')->nullable()->constrained('services'); // Service for daily billing
            $table->foreignId('admitted_by_staff_id')->constrained('users');
            $table->timestamp('arrival_time');
            
            // Release data
            $table->timestamp('release_time')->nullable();
            $table->foreignId('released_by_staff_id')->nullable()->constrained('users');
            $table->string('released_to_name')->nullable();
            $table->string('released_to_id_type')->nullable();
            $table->string('released_to_id_no')->nullable();
            
            $table->enum('status', ['stored', 'released'])->default('stored');
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
        Schema::dropIfExists('morgue_admissions');
    }
}
