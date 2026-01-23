<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.2
 * Leave Types with configurable constraints
 */
class CreateLeaveTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // "Annual Leave", "Sick Leave", etc.
            $table->string('code')->unique();                    // "AL", "SL", "ML", etc.
            $table->text('description')->nullable();
            $table->integer('max_days_per_year')->default(0);    // 0 = unlimited
            $table->integer('max_consecutive_days')->default(0); // 0 = no limit
            $table->integer('max_requests_per_year')->default(0);// 0 = unlimited
            $table->integer('min_days_notice')->default(0);      // Days before leave starts
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('color')->default('#3498db');         // For calendar display
            $table->json('applicable_employment_types')->nullable(); // ["full_time", "contract"]
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
        Schema::dropIfExists('leave_types');
    }
}
