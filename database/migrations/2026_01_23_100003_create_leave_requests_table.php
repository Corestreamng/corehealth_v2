<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.3
 * Leave Requests with Two-Level Approval Workflow:
 * 1. First Level: Unit Head (same department) OR Dept Head (same user category)
 * 2. Second Level: HR Manager (only after first level approved)
 */
class CreateLeaveRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->text('reason')->nullable();
            $table->text('handover_notes')->nullable();
            $table->unsignedBigInteger('relief_staff_id')->nullable(); // Who covers

            // Two-Level Approval Status Flow:
            // pending → supervisor_approved → approved (HR) / rejected / cancelled / recalled
            $table->enum('status', [
                'pending',              // Initial state, awaiting first-level approval
                'supervisor_approved',  // First level (unit/dept head) approved, awaiting HR
                'approved',             // HR approved (final)
                'rejected',             // Rejected at any stage
                'cancelled',            // Cancelled by staff
                'recalled'              // Recalled after approval
            ])->default('pending');

            // First Level Approval (Unit Head / Dept Head)
            $table->unsignedBigInteger('supervisor_approved_by')->nullable();
            $table->timestamp('supervisor_approved_at')->nullable();
            $table->text('supervisor_comments')->nullable();

            // Second Level Approval (HR Manager)
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->text('hr_comments')->nullable();

            // Combined/Final approval tracking (for backward compatibility)
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('leave_types');
            $table->foreign('relief_staff_id')->references('id')->on('staff')->nullOnDelete();
            $table->foreign('supervisor_approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hr_approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['staff_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_requests');
    }
}
