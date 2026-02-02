<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance Banks Table
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 1.2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.1
 *
 * Adds fields for:
 * - Balance tracking (via JE)
 * - Statement reconciliation
 * - Bank details
 * - Overdraft limits
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            // Link to GL Account (required for JE centricity) - only add if not exists
            if (!Schema::hasColumn('banks', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('id')
                    ->constrained('accounts')->nullOnDelete();
            }

            // Bank type classification
            if (!Schema::hasColumn('banks', 'bank_type')) {
                $table->enum('bank_type', ['current', 'savings', 'fixed_deposit', 'money_market'])
                    ->default('current')->after('bank_code');
            }

            // Statement reconciliation fields
            if (!Schema::hasColumn('banks', 'last_statement_date')) {
                $table->date('last_statement_date')->nullable()->after('bank_type');
            }
            if (!Schema::hasColumn('banks', 'last_statement_balance')) {
                $table->decimal('last_statement_balance', 15, 2)->nullable()->after('last_statement_date');
            }
            if (!Schema::hasColumn('banks', 'statement_closing_day')) {
                $table->tinyInteger('statement_closing_day')->default(25)->after('last_statement_balance');
            }

            // Limits and thresholds
            if (!Schema::hasColumn('banks', 'overdraft_limit')) {
                $table->decimal('overdraft_limit', 15, 2)->default(0)->after('statement_closing_day');
            }
            if (!Schema::hasColumn('banks', 'minimum_balance')) {
                $table->decimal('minimum_balance', 15, 2)->default(0)->after('overdraft_limit');
            }

            // Additional bank details
            if (!Schema::hasColumn('banks', 'swift_code')) {
                $table->string('swift_code', 20)->nullable()->after('minimum_balance');
            }
            if (!Schema::hasColumn('banks', 'branch_name')) {
                $table->string('branch_name')->nullable()->after('swift_code');
            }
            if (!Schema::hasColumn('banks', 'branch_code')) {
                $table->string('branch_code', 20)->nullable()->after('branch_name');
            }
            if (!Schema::hasColumn('banks', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('branch_code');
            }
            if (!Schema::hasColumn('banks', 'contact_phone')) {
                $table->string('contact_phone', 50)->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('banks', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('contact_phone');
            }

            // Signatory information
            if (!Schema::hasColumn('banks', 'signatories')) {
                $table->json('signatories')->nullable()->after('contact_email');
            }
        });

        // Add index for quick lookups - separate schema call after columns added
        // Skip index check to avoid Doctrine dependency
        try {
            Schema::table('banks', function (Blueprint $table) {
                $table->index(['bank_type', 'is_active']);
            });
        } catch (\Exception $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropIndex(['bank_type', 'is_active']);

            $table->dropColumn([
                'account_id',
                'bank_type',
                'last_statement_date',
                'last_statement_balance',
                'statement_closing_day',
                'overdraft_limit',
                'minimum_balance',
                'swift_code',
                'branch_name',
                'branch_code',
                'contact_person',
                'contact_phone',
                'contact_email',
                'signatories',
            ]);
        });
    }
};
