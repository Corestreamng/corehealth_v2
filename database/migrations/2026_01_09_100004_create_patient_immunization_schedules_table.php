<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_immunization_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('schedule_item_id')->constrained('vaccine_schedule_items')->cascadeOnDelete();
            $table->date('due_date'); // Calculated from patient DOB + age_days
            $table->date('administered_date')->nullable();
            $table->enum('status', ['pending', 'due', 'overdue', 'administered', 'skipped', 'contraindicated'])->default('pending');
            $table->foreignId('immunization_record_id')->nullable()->constrained('immunization_records')->nullOnDelete();
            $table->text('skip_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'schedule_item_id'], 'pat_imm_sched_unique');
            $table->index(['patient_id', 'status'], 'pat_imm_sched_status_idx');
            $table->index(['patient_id', 'due_date'], 'pat_imm_sched_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_immunization_schedules');
    }
};
