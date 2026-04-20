<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Enhancement - Add comprehensive staff fields
 * Covers all spreadsheet fields: personal, employment, licensing, confirmation, retirement
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Organizational structure
            if (!Schema::hasColumn('staff', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('department_id')->constrained('units')->nullOnDelete();
            }
            if (!Schema::hasColumn('staff', 'cadre_id')) {
                $table->foreignId('cadre_id')->nullable()->after('unit_id')->constrained('cadres')->nullOnDelete();
            }
            if (!Schema::hasColumn('staff', 'grade_level_id')) {
                $table->foreignId('grade_level_id')->nullable()->after('cadre_id')->constrained('grade_levels')->nullOnDelete();
            }
            if (!Schema::hasColumn('staff', 'entry_grade_level_id')) {
                $table->foreignId('entry_grade_level_id')->nullable()->after('grade_level_id')->constrained('grade_levels')->nullOnDelete();
            }

            // Professional licensing
            if (!Schema::hasColumn('staff', 'license_number')) {
                $table->string('license_number')->nullable()->after('entry_grade_level_id')->comment('MDCN or professional license number');
            }
            if (!Schema::hasColumn('staff', 'license_expiry_date')) {
                $table->date('license_expiry_date')->nullable()->after('license_number');
            }

            // National identity
            if (!Schema::hasColumn('staff', 'national_id_number')) {
                $table->string('national_id_number')->nullable()->after('license_expiry_date')->comment('NIN');
            }

            // Job location & responsibility
            if (!Schema::hasColumn('staff', 'job_location')) {
                $table->string('job_location')->nullable()->after('national_id_number');
            }
            if (!Schema::hasColumn('staff', 'responsibility')) {
                $table->text('responsibility')->nullable()->after('job_location');
            }

            // Personal details
            if (!Schema::hasColumn('staff', 'marital_status')) {
                $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed', 'separated'])->nullable()->after('responsibility');
            }
            if (!Schema::hasColumn('staff', 'number_of_children')) {
                $table->integer('number_of_children')->default(0)->after('marital_status');
            }
            if (!Schema::hasColumn('staff', 'permanent_home_address')) {
                $table->text('permanent_home_address')->nullable()->after('number_of_children');
            }
            if (!Schema::hasColumn('staff', 'other_talents')) {
                $table->text('other_talents')->nullable()->after('permanent_home_address');
            }

            // Confirmation tracking
            if (!Schema::hasColumn('staff', 'confirmation_due_date')) {
                $table->date('confirmation_due_date')->nullable()->after('date_confirmed');
            }

            // Retirement & exit planning (denormalized for fast queries)
            if (!Schema::hasColumn('staff', 'retirement_date')) {
                $table->date('retirement_date')->nullable()->after('other_talents')->comment('Expected retirement date');
            }
            if (!Schema::hasColumn('staff', 'max_service_date')) {
                $table->date('max_service_date')->nullable()->after('retirement_date')->comment('Expected exit by max service years');
            }

            // Promotion tracking (denormalized from staff_promotions)
            if (!Schema::hasColumn('staff', 'last_promotion_date')) {
                $table->date('last_promotion_date')->nullable()->after('max_service_date');
            }
            if (!Schema::hasColumn('staff', 'next_promotion_due_date')) {
                $table->date('next_promotion_due_date')->nullable()->after('last_promotion_date');
            }

            // Medical exam tracking (denormalized from staff_medical_exams)
            if (!Schema::hasColumn('staff', 'last_medical_exam_date')) {
                $table->date('last_medical_exam_date')->nullable()->after('next_promotion_due_date');
            }
            if (!Schema::hasColumn('staff', 'next_medical_exam_due')) {
                $table->date('next_medical_exam_due')->nullable()->after('last_medical_exam_date');
            }

            // Salary increment tracking
            if (!Schema::hasColumn('staff', 'salary_increment_date')) {
                $table->date('salary_increment_date')->nullable()->after('next_medical_exam_due');
            }

            // Indexes for registry queries
            $table->index('license_expiry_date', 'staff_license_expiry_idx');
            $table->index('confirmation_due_date', 'staff_confirmation_due_idx');
            $table->index('next_promotion_due_date', 'staff_promotion_due_idx');
            $table->index('next_medical_exam_due', 'staff_medical_exam_due_idx');
            $table->index('retirement_date', 'staff_retirement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('staff_license_expiry_idx');
            $table->dropIndex('staff_confirmation_due_idx');
            $table->dropIndex('staff_promotion_due_idx');
            $table->dropIndex('staff_medical_exam_due_idx');
            $table->dropIndex('staff_retirement_idx');

            // Drop foreign keys and columns
            $columns = [
                'unit_id', 'cadre_id', 'grade_level_id', 'entry_grade_level_id',
                'license_number', 'license_expiry_date', 'national_id_number',
                'job_location', 'responsibility',
                'marital_status', 'number_of_children', 'permanent_home_address', 'other_talents',
                'confirmation_due_date',
                'retirement_date', 'max_service_date',
                'last_promotion_date', 'next_promotion_due_date',
                'last_medical_exam_date', 'next_medical_exam_due',
                'salary_increment_date',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('staff', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
