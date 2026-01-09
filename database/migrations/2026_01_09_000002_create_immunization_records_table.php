<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('immunization_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_id'); // Vaccine product
            $table->unsignedBigInteger('product_or_service_request_id')->nullable(); // Links to billing
            $table->string('vaccine_name', 200);
            $table->integer('dose_number')->default(1); // 1st, 2nd, 3rd dose etc.
            $table->string('dose', 100)->nullable();
            $table->enum('route', ['IM', 'SC', 'Oral', 'ID'])->default('IM');
            $table->string('site', 100)->nullable(); // Injection site
            $table->datetime('administered_at');
            $table->unsignedBigInteger('administered_by');
            $table->string('batch_number', 50)->nullable();
            $table->string('manufacturer', 200)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('adverse_reaction')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_or_service_request_id')->references('id')->on('product_or_service_requests')->onDelete('set null');
            $table->foreign('administered_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes for faster queries
            $table->index(['patient_id', 'administered_at']);
            $table->index(['patient_id', 'vaccine_name']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immunization_records');
    }
};
