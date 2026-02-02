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
        Schema::table('patient_deposit_applications', function (Blueprint $table) {
            // Add status column - tracks if application is active or reversed
            $table->enum('status', ['applied', 'reversed'])
                ->default('applied')
                ->after('applied_by');

            // Add reversal tracking columns
            $table->text('reversal_reason')->nullable()->after('status');
            $table->foreignId('reversed_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->after('reversal_reason');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');

            // Add application_type column
            $table->enum('application_type', ['bill_payment', 'refund'])
                ->default('bill_payment')
                ->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_deposit_applications', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'reversal_reason',
                'reversed_by',
                'reversed_at',
                'application_type'
            ]);
        });
    }
};
