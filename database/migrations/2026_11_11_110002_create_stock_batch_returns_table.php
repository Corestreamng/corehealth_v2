<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('stock_batch_returns')) {
            Schema::create('stock_batch_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 50)->unique();

                // Batch & Product context
                $table->unsignedBigInteger('stock_batch_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('supplier_id')->nullable();

                // Quantities & Value
                $table->integer('qty_returned');
                $table->decimal('unit_cost', 12, 2)->comment('Cost price per unit at return time');
                $table->decimal('total_value', 12, 2)->comment('Total value of returned goods');

                // Reason & Settlement Mode
                $table->string('return_reason', 100)->comment('damaged, expired_near, recalled, wrong_item, excess, quality_issue, other');
                $table->string('settlement_type', 50)->default('ap_credit_note')->comment('ap_credit_note, cash_refund, receivable, no_financial_impact');
                $table->text('return_notes')->nullable();

                // Workflow & Authorization
                $table->string('status', 50)->default('approved')->comment('pending, approved, rejected');
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_notes')->nullable();

                // Stock & Financial Deductions
                $table->boolean('stock_deducted')->default(false);
                $table->timestamp('stock_deducted_at')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Foreign Keys
                $table->foreign('stock_batch_id')->references('id')->on('stock_batches')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

                // Indexes
                $table->index(['store_id', 'status'], 'idx_batch_returns_store_status');
                $table->index('supplier_id', 'idx_batch_returns_supplier');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batch_returns');
    }
};
