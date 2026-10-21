<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');

            // Original snapshot
            $table->string('original_payment_method', 50);
            $table->unsignedBigInteger('original_bank_id')->nullable();
            $table->boolean('original_is_split')->default(false);
            $table->json('original_splits')->nullable();

            // New values
            $table->string('new_payment_method', 50);
            $table->unsignedBigInteger('new_bank_id')->nullable();
            $table->boolean('new_is_split')->default(false);
            $table->json('new_splits')->nullable();

            // Accounting
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->unsignedBigInteger('new_journal_entry_id')->nullable();

            // Audit
            $table->unsignedBigInteger('adjusted_by');
            $table->text('reason');
            $table->timestamp('adjusted_at')->useCurrent();

            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments');
            $table->foreign('adjusted_by')->references('id')->on('users');
            $table->index('payment_id', 'idx_pa_payment');
            $table->index('adjusted_by', 'idx_pa_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_adjustments');
    }
};
