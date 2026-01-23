<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.13
 * Payroll Item Details - Line items for each staff's payroll
 */
class CreatePayrollItemDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_item_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_item_id');
            $table->unsignedBigInteger('pay_head_id');
            $table->enum('type', ['addition', 'deduction']);
            $table->string('pay_head_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->cascadeOnDelete();
            $table->foreign('pay_head_id')->references('id')->on('pay_heads');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_item_details');
    }
}
