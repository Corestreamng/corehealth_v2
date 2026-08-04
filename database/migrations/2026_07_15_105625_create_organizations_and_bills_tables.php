<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates billing organizations, organization_bills (mirrors staff_bills),
     * and organization_bill_payment_allocations (mirrors staff_bill_payment_allocations).
     */
    public function up(): void
    {
        // 1. Billing Organizations table
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->boolean('status')->default(true); // active/inactive
                $table->timestamps();

                $table->index('name');
                $table->index('status');
            });
        }

        // 2. Organization Bills — mirrors staff_bills structure exactly
        if (!Schema::hasTable('organization_bills')) {
            Schema::create('organization_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete(); // Checkout payment
                $table->decimal('total_amount', 12, 2);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('outstanding_amount', 12, 2);
                $table->enum('status', ['pending_audit', 'pending', 'paid', 'rejected'])->default('pending_audit');
                $table->foreignId('settlement_payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('organization_id');
                $table->index('payment_id');
                $table->index('patient_id');
            });
        }

        // 3. Organization Bill Payment Allocations — mirrors staff_bill_payment_allocations
        if (!Schema::hasTable('org_bill_pay_allocs')) {
            Schema::create('org_bill_pay_allocs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_bill_id');
                $table->unsignedBigInteger('payment_id');
                $table->decimal('amount_allocated', 12, 2);
                $table->decimal('discount_allocated', 12, 2)->default(0.00); // For partial/full waivers
                $table->timestamps();

                $table->foreign('organization_bill_id', 'obpa_org_bill_fk')
                    ->references('id')->on('organization_bills')->cascadeOnDelete();
                $table->foreign('payment_id', 'obpa_payment_fk')
                    ->references('id')->on('payments')->cascadeOnDelete();

                $table->index('organization_bill_id', 'obpa_org_bill_idx');
                $table->index('payment_id', 'obpa_payment_idx');
            });
        }


        // 4. Seed GL Account Code 1131 under Group 11 (Receivables) for organization receivables
        $group = DB::table('account_groups')->where('code', '11')->first();
        if ($group) {
            DB::table('accounts')->updateOrInsert(
                ['code' => '1131'],
                [
                    'account_group_id' => $group->id,
                    'name' => 'Accounts Receivable - Organizations',
                    'description' => 'Outstanding receivables from billed organizations',
                    'is_system' => true,
                    'is_active' => true,
                    'is_bank_account' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_bill_pay_allocs');
        Schema::dropIfExists('organization_bills');
        Schema::dropIfExists('organizations');

        DB::table('accounts')->where('code', '1131')->delete();
    }
};
