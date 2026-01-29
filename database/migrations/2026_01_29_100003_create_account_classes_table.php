<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account Classes Table Migration
 *
 * Reference: Accounting System Plan §3.2 - Chart of Accounts Tables
 *
 * Stores the five main account classifications:
 * - ASSET (normal balance: debit)
 * - LIABILITY (normal balance: credit)
 * - EQUITY (normal balance: credit)
 * - INCOME (normal balance: credit, temporary)
 * - EXPENSE (normal balance: debit, temporary)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();               // "1", "2", "3", "4", "5"
            $table->string('name', 50);                         // "ASSET", "LIABILITY", etc.
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->tinyInteger('display_order');
            $table->boolean('is_temporary')->default(false);    // TRUE for INCOME, EXPENSE
            $table->enum('cash_flow_category', ['operating', 'investing', 'financing'])->nullable();
            $table->timestamps();

            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_classes');
    }
};
