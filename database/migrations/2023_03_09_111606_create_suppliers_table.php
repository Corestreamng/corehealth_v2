<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->float('last_payment')->nullable();
            $table->timestamp('last_payment_date')->nullable();
            $table->timestamp('last_buy_date')->nullable();
            $table->float('last_buy_amount')->nullable();
            $table->float('credit_b4')->nullable();
            $table->float('credit')->nullable();
            $table->float('deposit_b4')->nullable();
            $table->float('deposit')->nullable();
            $table->float('total_deposite')->nullable();//back compatability
            $table->timestamp('date_line')->useCurrent();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
}
