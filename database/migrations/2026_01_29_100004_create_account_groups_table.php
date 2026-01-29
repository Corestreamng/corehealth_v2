<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account Groups Table Migration
 *
 * Reference: Accounting System Plan §3.2 - Chart of Accounts Tables
 *
 * Stores account groups within each class, e.g.:
 * - Current Assets, Fixed Assets (under ASSET)
 * - Current Liabilities, Long-term Liabilities (under LIABILITY)
 * - Operating Revenue, Other Income (under INCOME)
 * - Cost of Sales, Operating Expenses (under EXPENSE)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_class_id')->constrained('account_classes')->cascadeOnDelete();
            $table->string('code', 20)->unique();               // "1.1", "1.2", "2.1"
            $table->string('name', 100);                        // "Current Assets", "Operating Revenue"
            $table->text('description')->nullable();
            $table->tinyInteger('display_order');
            $table->timestamps();

            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_groups');
    }
};
