<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add liability_account_id to pay_heads table.
 *
 * This allows HR to link deduction pay heads (PAYE, Pension, etc.)
 * to their corresponding liability GL accounts. When payroll is
 * approved, deductions will be posted as separate credit lines
 * to these accounts instead of being bundled into Salaries Payable.
 *
 * Example:
 * - PAYE Tax → 2060 PAYE Payable
 * - Pension → 2040 Pension Payable
 * - Staff Loan → 2080 Staff Loans Recoverable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_heads', function (Blueprint $table) {
            $table->unsignedBigInteger('liability_account_id')->nullable()->after('is_active');

            $table->foreign('liability_account_id')
                  ->references('id')
                  ->on('accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pay_heads', function (Blueprint $table) {
            $table->dropForeign(['liability_account_id']);
            $table->dropColumn('liability_account_id');
        });
    }
};
