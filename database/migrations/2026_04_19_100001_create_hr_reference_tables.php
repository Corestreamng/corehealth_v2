<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Enhancement - Reference Tables
 * Creates units, cadres, and grade_levels tables for organizational structure
 */
return new class extends Migration
{
    public function up(): void
    {
        // Units table - organizational units within departments
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('head_of_unit_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'department_id']);
        });

        // Cadres table - staff classification/cadre categories
        Schema::create('cadres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Grade levels table - civil service style grading with retirement/service rules
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('level');
            $table->integer('step')->default(1);
            $table->text('description')->nullable();
            $table->integer('min_years_to_next')->nullable()->comment('Minimum years before promotion to next level');
            $table->integer('retirement_age')->nullable()->comment('Mandatory retirement age for this level');
            $table->integer('max_years_of_service')->nullable()->comment('Maximum years of service allowed');
            $table->decimal('min_salary', 15, 2)->nullable()->comment('Minimum salary band for this level');
            $table->decimal('max_salary', 15, 2)->nullable()->comment('Maximum salary band for this level');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['level', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('cadres');
        Schema::dropIfExists('units');
    }
};
