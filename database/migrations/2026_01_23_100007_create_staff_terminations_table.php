<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.7
 * Staff Terminations with exit process tracking
 */
class CreateStaffTerminationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_terminations', function (Blueprint $table) {
            $table->id();
            $table->string('termination_number')->unique();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('disciplinary_query_id')->nullable();

            $table->enum('type', ['voluntary', 'involuntary', 'retirement', 'death', 'contract_end'])->default('voluntary');
            $table->enum('reason_category', [
                'resignation', 'misconduct', 'poor_performance', 'redundancy',
                'retirement', 'medical', 'death', 'contract_expiry', 'other'
            ]);
            $table->text('reason_details');
            $table->date('notice_date');
            $table->date('effective_date');
            $table->date('last_working_day');

            // Exit Details
            $table->boolean('exit_interview_conducted')->default(false);
            $table->text('exit_interview_notes')->nullable();
            $table->boolean('clearance_completed')->default(false);
            $table->boolean('final_payment_processed')->default(false);

            $table->unsignedBigInteger('processed_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('disciplinary_query_id')->references('id')->on('disciplinary_queries')->nullOnDelete();
            $table->foreign('processed_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_terminations');
    }
}
