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
        Schema::create('hmo_tariffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hmo_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->decimal('claims_amount', 10, 2)->default(0)->comment('Amount the HMO will pay');
            $table->decimal('payable_amount', 10, 2)->comment('Amount the patient must pay');
            $table->enum('coverage_mode', ['express', 'primary', 'secondary'])->default('primary')->comment('express: auto-approved, primary: requires validation, secondary: requires validation + auth code');
            $table->timestamps();

            // Foreign keys
            $table->foreign('hmo_id')->references('id')->on('hmos')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            // Unique constraint: one tariff per HMO-Product or HMO-Service combination
            $table->unique(['hmo_id', 'product_id', 'service_id'], 'unique_hmo_product_service');

            // Check constraint: either product_id OR service_id must be set, not both
            // Note: Laravel doesn't support CHECK constraints directly in migrations, handle in application logic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hmo_tariffs');
    }
};
