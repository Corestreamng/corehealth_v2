<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePharmacyDamagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pharmacy_damages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id'); // Store where damage occurred
            $table->unsignedBigInteger('batch_id')->nullable(); // Specific batch if known

            // Damage details
            $table->decimal('qty_damaged', 10, 2);
            $table->decimal('unit_cost', 10, 2)->comment('Cost per unit');
            $table->decimal('total_value', 10, 2)->comment('Total loss value');
            $table->string('damage_type', 50)->comment('expired, broken, contaminated, spoiled, theft, other');
            $table->text('damage_reason');
            $table->date('discovered_date');

            // Workflow
            $table->string('status', 50)->default('pending')->comment('pending, approved, rejected, written_off');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Journal Entry tracking
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            // Stock deduction tracking
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('stock_deducted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('damage_type');
            $table->index('discovered_date');
            $table->index(['store_id', 'discovered_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pharmacy_damages');
    }
}
