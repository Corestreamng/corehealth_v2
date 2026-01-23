<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.4
 * Leave Balances for tracking entitlements per year
 */
class CreateLeaveBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->decimal('entitled_days', 5, 1)->default(0);    // Total allocation
            $table->decimal('used_days', 5, 1)->default(0);        // Days taken
            $table->decimal('pending_days', 5, 1)->default(0);     // Pending approval
            $table->decimal('carried_forward', 5, 1)->default(0);  // From previous year
            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('leave_types');

            $table->unique(['staff_id', 'leave_type_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_balances');
    }
}
