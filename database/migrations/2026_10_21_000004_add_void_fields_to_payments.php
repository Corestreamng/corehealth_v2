<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('void_reversal_je_id')->nullable();
            $table->index('voided_at', 'idx_payments_voided');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_voided');
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason', 'void_reversal_je_id']);
        });
    }
};
