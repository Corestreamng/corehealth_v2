<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create staff_bills table
        Schema::create('staff_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete(); // Billed staff member user record
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete(); // Billed payment
            $table->decimal('total_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->foreignId('settlement_payment_id')->nullable()->constrained('payments')->nullOnDelete(); // Settlement payment
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('staff_user_id');
            $table->index('payment_id');
        });

        // 2. Seed GL Account Code 1130 under Group 11 (Receivables)
        $group = DB::table('account_groups')->where('code', '11')->first();
        if ($group) {
            DB::table('accounts')->updateOrInsert(
                ['code' => '1130'],
                [
                    'account_group_id' => $group->id,
                    'name' => 'Accounts Receivable - Staff',
                    'description' => 'Outstanding receivables from billed staff members',
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
        Schema::dropIfExists('staff_bills');

        DB::table('accounts')->where('code', '1130')->delete();
    }
};
