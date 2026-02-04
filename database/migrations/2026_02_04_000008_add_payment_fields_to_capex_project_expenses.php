<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentFieldsToCapexProjectExpenses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('capex_project_expenses', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'card'])->default('bank_transfer')->after('invoice_number');
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
            $table->string('cheque_number')->nullable()->after('bank_id');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('capex_project_expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['payment_method', 'bank_id', 'cheque_number']);
        });
    }
}
