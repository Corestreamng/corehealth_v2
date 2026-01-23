<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRMS Implementation Plan - Section 4.1.1
 * Add HR-specific fields to existing staff table
 */
class AddHrFieldsToStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            // Employment Information
            if (!Schema::hasColumn('staff', 'employee_id')) {
                $table->string('employee_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('staff', 'date_hired')) {
                $table->date('date_hired')->nullable()->after('status');
            }
            if (!Schema::hasColumn('staff', 'date_confirmed')) {
                $table->date('date_confirmed')->nullable()->after('date_hired');
            }
            if (!Schema::hasColumn('staff', 'employment_type')) {
                $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time')->after('date_confirmed');
            }
            if (!Schema::hasColumn('staff', 'employment_status')) {
                $table->enum('employment_status', ['active', 'suspended', 'terminated', 'resigned'])->default('active')->after('employment_type');
            }

            // Bank Information
            if (!Schema::hasColumn('staff', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('employment_status');
            }
            if (!Schema::hasColumn('staff', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('staff', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }

            // Emergency Contact
            if (!Schema::hasColumn('staff', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('staff', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('staff', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
            }

            // Tax & Pension
            if (!Schema::hasColumn('staff', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('emergency_contact_relationship');
            }
            if (!Schema::hasColumn('staff', 'pension_id')) {
                $table->string('pension_id')->nullable()->after('tax_id');
            }

            // HR Notes
            if (!Schema::hasColumn('staff', 'hr_notes')) {
                $table->text('hr_notes')->nullable()->after('pension_id');
            }

            // Suspension details (for login blocking)
            if (!Schema::hasColumn('staff', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('hr_notes');
            }
            if (!Schema::hasColumn('staff', 'suspended_by')) {
                $table->unsignedBigInteger('suspended_by')->nullable()->after('suspended_at');
            }
            if (!Schema::hasColumn('staff', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_by');
            }
            if (!Schema::hasColumn('staff', 'suspension_end_date')) {
                $table->date('suspension_end_date')->nullable()->after('suspension_reason');
            }
        });

        // Add foreign key if it doesn't exist
        if (Schema::hasColumn('staff', 'suspended_by')) {
            try {
                Schema::table('staff', function (Blueprint $table) {
                    $table->foreign('suspended_by')->references('id')->on('users')->nullOnDelete();
                });
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);

            $table->dropColumn([
                'employee_id', 'date_hired', 'date_confirmed', 'employment_type', 'employment_status',
                'bank_name', 'bank_account_number', 'bank_account_name',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                'tax_id', 'pension_id', 'hr_notes',
                'suspended_at', 'suspended_by', 'suspension_reason', 'suspension_end_date'
            ]);
        });
    }
}
