<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Expenses Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Track all expenses including PO-related purchases and store expenses
 *
 * Key Features:
 * - Polymorphic reference to link to PO or other sources
 * - Category-based expense tracking
 * - Approval workflow for expenses
 *
 * Related Models: Expense, PurchaseOrder, Supplier, User
 * Related Files:
 * - app/Models/Expense.php
 * - app/Http/Controllers/ExpenseController.php
 */
class CreateExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->enum('category', ['purchase_order', 'store_expense', 'maintenance', 'utilities', 'salaries', 'other'])->default('other');
            $table->string('reference_type')->nullable(); // Polymorphic: App\Models\PurchaseOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of referenced record
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable(); // Which store incurred this expense
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('expense_date');
            $table->unsignedBigInteger('recorded_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'void'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('payment_method')->nullable(); // cash, bank_transfer, cheque, etc.
            $table->string('payment_reference')->nullable(); // cheque number, transaction ID, etc.
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['category', 'status']);
            $table->index(['expense_date', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['store_id', 'expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expenses');
    }
}
