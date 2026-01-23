<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.9
 * Staff Salary Profiles with versioning
 */
class CreateStaffSalaryProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('staff_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->enum('pay_frequency', ['monthly', 'bi_weekly', 'weekly'])->default('monthly');
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // Null = current profile
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['staff_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff_salary_profiles');
    }
}
