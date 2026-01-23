<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.6
 * Staff Suspensions with login blocking support
 */
class CreateStaffSuspensionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_suspensions', function (Blueprint $table) {
            $table->id();
            $table->string('suspension_number')->unique();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('disciplinary_query_id')->nullable(); // Link to query if applicable
            $table->enum('type', ['paid', 'unpaid'])->default('unpaid');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null = indefinite
            $table->text('reason');
            $table->text('suspension_message'); // Message shown to staff on login attempt

            $table->enum('status', ['active', 'lifted', 'expired'])->default('active');

            // Lifted early
            $table->unsignedBigInteger('lifted_by')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->text('lift_reason')->nullable();

            // Issuer
            $table->unsignedBigInteger('issued_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('disciplinary_query_id')->references('id')->on('disciplinary_queries')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users');
            $table->foreign('lifted_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_suspensions');
    }
}
