<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.8
 * Pay Heads for Additions and Deductions
 */
class CreatePayHeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pay_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // "Basic Salary", "Housing Allowance", "Tax", etc.
            $table->string('code')->unique();                    // "BASIC", "HOUSING", "TAX", "PENSION"
            $table->text('description')->nullable();
            $table->enum('type', ['addition', 'deduction']);     // Earnings vs Deductions
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
            $table->string('calculation_base')->nullable();      // For percentage: "basic_salary", "gross_salary"
            $table->decimal('default_value', 15, 2)->default(0); // Default amount or percentage
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_mandatory')->default(false);     // Must be in every payroll
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('pay_heads');
    }
}
