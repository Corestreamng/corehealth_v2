<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Treatment Plans migration (CLINICAL_ORDERS_PLAN §6.1).
     * Creates treatment_plans and treatment_plan_items tables.
     */
    public function up()
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // e.g. "Malaria Protocol (Adult)"
            $table->text('description')->nullable();
            $table->string('specialty', 100)->nullable();              // optional specialty filter
            $table->unsignedBigInteger('created_by');                  // users.id
            $table->boolean('is_global')->default(false);              // visible to all or just creator
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['status', 'is_global']);
            $table->index('created_by');
        });

        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('treatment_plan_id');
            $table->enum('item_type', ['lab', 'imaging', 'medication', 'procedure']);
            $table->unsignedBigInteger('reference_id');                // services.id or products.id
            $table->string('dose', 500)->nullable();                   // medication only (pipe-delimited)
            $table->text('note')->nullable();                          // labs/imaging clinical note, procedure pre_notes
            $table->string('priority', 20)->nullable();                // procedure only
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('cascade');
            $table->index(['treatment_plan_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('treatment_plan_items');
        Schema::dropIfExists('treatment_plans');
    }
};
