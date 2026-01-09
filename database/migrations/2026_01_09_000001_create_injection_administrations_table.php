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
        Schema::create('injection_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_or_service_request_id')->nullable(); // Links to billing
            $table->string('dose', 100);
            $table->enum('route', ['IM', 'IV', 'SC', 'ID'])->default('IM');
            $table->string('site', 100)->nullable(); // Injection site (e.g., Left Deltoid)
            $table->datetime('administered_at');
            $table->unsignedBigInteger('administered_by');
            $table->text('notes')->nullable();
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_or_service_request_id')->references('id')->on('product_or_service_requests')->onDelete('set null');
            $table->foreign('administered_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes for faster queries
            $table->index(['patient_id', 'administered_at']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('injection_administrations');
    }
};
