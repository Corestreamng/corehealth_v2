<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hmo_remittances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hmo_id');
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->string('payment_method')->nullable(); // bank_transfer, cheque, cash
            $table->string('bank_name')->nullable();
            $table->date('payment_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_file')->nullable(); // uploaded receipt/proof
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('hmo_id')->references('id')->on('hmos')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['hmo_id', 'payment_date']);
            $table->index('reference_number');
        });

        // Add remittance tracking columns to product_or_service_requests if not exists
        if (!Schema::hasColumn('product_or_service_requests', 'hmo_remittance_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('hmo_remittance_id')->nullable()->after('payment_id');
                $table->timestamp('submitted_to_hmo_at')->nullable()->after('validation_notes');
                $table->string('hmo_submission_batch')->nullable()->after('submitted_to_hmo_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove columns from product_or_service_requests first
        if (Schema::hasColumn('product_or_service_requests', 'hmo_remittance_id')) {
            Schema::table('product_or_service_requests', function (Blueprint $table) {
                $table->dropColumn(['hmo_remittance_id', 'submitted_to_hmo_at', 'hmo_submission_batch']);
            });
        }

        Schema::dropIfExists('hmo_remittances');
    }
};
