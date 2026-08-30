<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillingQueuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('billing_queues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('patient_id')->nullable();

            $table->integer('unpaid_items_count')->default(0);
            $table->integer('hmo_items_count')->default(0);

            $table->boolean('is_emergency')->default(false);
            $table->timestamp('latest_item_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            // Indexes for sorting/filtering the queue
            $table->index(['is_emergency', 'latest_item_at']);
            $table->index('latest_item_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('billing_queues');
    }
}
