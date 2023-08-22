<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMiscBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('misc_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->nullable()->constrained('product_or_service_requests');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('creation_date')->nullable();
            $table->foreignId('billed_by')->nullable()->constrained('users');
            $table->timestamp('billed_date')->nullable();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('patient_id')->constrained('patients');
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
        Schema::dropIfExists('misc_bills');
    }
}
