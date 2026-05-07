<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderReturnsTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();

            // PO reference
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('purchase_order_item_id');

            // Product & batch
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id'); // Store stock is deducted from
            $table->unsignedBigInteger('batch_id')->nullable(); // Batch to return from

            // Return details
            $table->integer('qty_returned');
            $table->decimal('unit_cost', 10, 2)->comment('Cost per unit at time of return');
            $table->decimal('total_value', 10, 2)->comment('Total value being returned');
            $table->string('return_reason', 100)->comment('wrong_item, damaged, excess, quality_issue, other');
            $table->text('return_notes')->nullable();

            // Workflow
            $table->string('status', 50)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Accounting / expense adjustment
            $table->string('payment_status_at_return', 50)->nullable()->comment('Snapshot of PO payment status: unpaid, partial, paid');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->boolean('expense_adjusted')->default(false)->comment('Prevents duplicate JE creation');
            $table->timestamp('expense_adjusted_at')->nullable();

            // Stock tracking
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('stock_deducted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('purchase_order_id');
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_returns');
    }
}
