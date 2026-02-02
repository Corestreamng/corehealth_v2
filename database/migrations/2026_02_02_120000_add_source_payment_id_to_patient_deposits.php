<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add source_payment_id to patient_deposits table
 *
 * This links PatientDeposit records to their source Payment records
 * when deposits are created via BillingWorkbench (legacy flow).
 *
 * This allows the PaymentObserver to skip JE creation for ACC_DEPOSIT
 * payments that have a corresponding PatientDeposit (which creates its own JE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_deposits', function (Blueprint $table) {
            // Link to the source payment record (from legacy BillingWorkbench flow)
            $table->unsignedBigInteger('source_payment_id')->nullable()->after('journal_entry_id');

            // Index for quick lookup
            $table->index('source_payment_id');

            // Foreign key (optional - soft constraint)
            $table->foreign('source_payment_id')
                  ->references('id')
                  ->on('payments')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('patient_deposits', function (Blueprint $table) {
            $table->dropForeign(['source_payment_id']);
            $table->dropIndex(['source_payment_id']);
            $table->dropColumn('source_payment_id');
        });
    }
};
