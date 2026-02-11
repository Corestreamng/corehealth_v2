<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharmacyReturnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pharmacy_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_request_id'); // Original dispensed item
            $table->unsignedBigInteger('product_or_service_request_id'); // Original billing record
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id'); // Store to return to
            $table->unsignedBigInteger('batch_id')->nullable(); // Batch to return to (if restockable)

            // Return details
            $table->decimal('qty_returned', 10, 2);
            $table->decimal('original_qty', 10, 2);
            $table->decimal('refund_amount', 10, 2);
            $table->decimal('original_amount', 10, 2);
            $table->string('return_condition', 50)->comment('good, damaged, expired');
            $table->text('return_reason');
            $table->boolean('restock')->default(false)->comment('Can item be restocked?');

            // Financial split (for HMO)
            $table->decimal('refund_to_patient', 10, 2)->default(0);
            $table->decimal('refund_to_hmo', 10, 2)->default(0);

            // Workflow
            $table->string('status', 50)->default('pending')->comment('pending, approved, rejected, completed');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Journal Entry tracking
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('product_request_id')->references('id')->on('product_requests')->onDelete('cascade');
            $table->foreign('product_or_service_request_id')->references('id')->on('product_or_service_requests')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

            // Indexes for filtering
            $table->index('status');
            $table->index('created_at');
            $table->index(['patient_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pharmacy_returns');
    }
}
