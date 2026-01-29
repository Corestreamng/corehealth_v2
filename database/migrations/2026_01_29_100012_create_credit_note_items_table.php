<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit Note Items Table Migration
 *
 * Reference: Accounting System Plan §3.5 - Credit Note Tables
 *
 * Stores individual line items for credit notes.
 * Each item references a product_or_service_request from the original payment.
 * The refund amount per item cannot exceed the original paid amount.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('product_or_service_request_id')->constrained('product_or_service_requests');
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('product_or_service_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
