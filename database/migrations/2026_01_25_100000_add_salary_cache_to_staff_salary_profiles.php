<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_salary_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_salary_profiles', 'gross_salary')) {
                $table->decimal('gross_salary', 15, 2)->nullable()->after('basic_salary');
            }
            if (!Schema::hasColumn('staff_salary_profiles', 'total_deductions')) {
                $table->decimal('total_deductions', 15, 2)->nullable()->after('gross_salary');
            }
            if (!Schema::hasColumn('staff_salary_profiles', 'net_salary')) {
                $table->decimal('net_salary', 15, 2)->nullable()->after('total_deductions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_salary_profiles', function (Blueprint $table) {
            $table->dropColumn(['gross_salary', 'total_deductions', 'net_salary']);
        });
    }
};
