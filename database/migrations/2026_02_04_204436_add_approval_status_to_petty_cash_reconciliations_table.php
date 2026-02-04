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
        Schema::table('petty_cash_reconciliations', function (Blueprint $table) {
            // Add approval workflow columns after status
            $table->enum('approval_status', ['pending_approval', 'approved', 'rejected'])
                ->default('pending_approval')
                ->after('status')
                ->comment('Workflow status for variance approval');

            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_reconciliations', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'rejection_reason']);
        });
    }
};
