<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account Sub-Accounts Table Migration
 *
 * Reference: Accounting System Plan §3.2 - Chart of Accounts Tables
 *
 * Stores sub-accounts (subsidiary ledgers) for detailed tracking:
 * - Patient sub-accounts under Accounts Receivable or Patient Deposits
 * - Supplier sub-accounts under Accounts Payable
 * - Product/Service sub-accounts under Revenue accounts
 * - Category-based sub-accounts for product/service categories
 *
 * Only one of the polymorphic fields should be set per record.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_sub_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('code', 30);                         // "1100.PAT.001", "4100.SVC.001"
            $table->string('name', 150);                        // "John Doe", "Consultation", "Paracetamol"

            // Polymorphic linking (only one should be set per record)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'code']);
            $table->index('product_id');
            $table->index('service_id');
            $table->index('product_category_id');
            $table->index('service_category_id');
            $table->index('supplier_id');
            $table->index('patient_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_sub_accounts');
    }
};
