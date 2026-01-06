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
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->decimal('payable_amount', 10, 2)->nullable()->after('discount')->comment('Amount patient must pay (from HMO tariff)');
            $table->decimal('claims_amount', 10, 2)->nullable()->default(0)->after('payable_amount')->comment('Amount HMO will pay (from tariff)');
            $table->string('coverage_mode', 20)->nullable()->after('claims_amount')->comment('express, primary, or secondary');
            $table->enum('validation_status', ['pending', 'approved', 'rejected'])->nullable()->after('coverage_mode')->comment('HMO validation status');
            $table->string('auth_code', 100)->nullable()->after('validation_status')->comment('Authorization code for secondary coverage');
            $table->unsignedBigInteger('validated_by')->nullable()->after('auth_code')->comment('User ID of HMO executive who validated');
            $table->timestamp('validated_at')->nullable()->after('validated_by')->comment('When validation occurred');
            $table->text('validation_notes')->nullable()->after('validated_at')->comment('Notes from HMO executive during validation');

            // Foreign key
            $table->foreign('validated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn([
                'payable_amount',
                'claims_amount',
                'coverage_mode',
                'validation_status',
                'auth_code',
                'validated_by',
                'validated_at',
                'validation_notes'
            ]);
        });
    }
};
