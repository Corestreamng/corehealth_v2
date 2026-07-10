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
        Schema::create('tariff_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hmo_id')->nullable();
            $table->unsignedBigInteger('hmo_scheme_id')->nullable();
            
            // e.g. 'product', 'service', 'product_category', 'service_category'
            $table->string('target_type'); 
            $table->unsignedBigInteger('target_id');
            
            // e.g. 'percentage', 'fixed'
            $table->enum('override_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('amount', 12, 2)->default(0)->comment('Percentage value or fixed naira amount for payable_amount');
            
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->foreign('hmo_id')->references('id')->on('hmos')->onDelete('cascade');
            $table->foreign('hmo_scheme_id')->references('id')->on('hmo_schemes')->onDelete('cascade');
            
            // To ensure we don't have duplicate rules for the same target and hmo/scheme
            $table->unique(['hmo_id', 'hmo_scheme_id', 'target_type', 'target_id'], 'tariff_overrides_unique_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_overrides');
    }
};
