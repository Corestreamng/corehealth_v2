<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Store Requisitions Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Inter-store stock transfer requests
 *
 * Key Features:
 * - Users can request items from one store to another
 * - Approval workflow (pending -> approved/rejected -> fulfilled)
 * - Tracks who requested, approved, and fulfilled
 *
 * Related Models: StoreRequisition, Store, User
 * Related Files:
 * - app/Models/StoreRequisition.php
 * - app/Services/RequisitionService.php
 * - app/Http/Controllers/StoreRequisitionController.php
 */
class CreateStoreRequisitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('store_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->unique();
            $table->unsignedBigInteger('from_store_id'); // Source store (where items come from)
            $table->unsignedBigInteger('to_store_id'); // Destination store (where items go to)
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'partial', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('request_notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('from_store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('to_store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('fulfilled_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['from_store_id', 'status']);
            $table->index(['to_store_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('store_requisitions');
    }
}
