<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.10
 * Staff Salary Profile Items - Pay heads mapped to profiles
 */
class CreateStaffSalaryProfileItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_salary_profile_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_profile_id');
            $table->unsignedBigInteger('pay_head_id');
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
            $table->string('calculation_base')->nullable();
            $table->decimal('value', 15, 4)->default(0); // Amount or percentage value
            $table->timestamps();

            $table->foreign('salary_profile_id')->references('id')->on('staff_salary_profiles')->cascadeOnDelete();
            $table->foreign('pay_head_id')->references('id')->on('pay_heads');

            $table->unique(['salary_profile_id', 'pay_head_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_salary_profile_items');
    }
}
