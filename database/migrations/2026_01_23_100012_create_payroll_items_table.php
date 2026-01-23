<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.12
 * Payroll Items - Individual staff entries in a batch
 */
class CreatePayrollItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_batch_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('salary_profile_id');

            // Summary
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('total_additions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);

            // Bank Details (snapshot at time of payroll)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();

            $table->timestamps();

            $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches')->cascadeOnDelete();
            $table->foreign('staff_id')->references('id')->on('staff');
            $table->foreign('salary_profile_id')->references('id')->on('staff_salary_profiles');

            $table->unique(['payroll_batch_id', 'staff_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_items');
    }
}
