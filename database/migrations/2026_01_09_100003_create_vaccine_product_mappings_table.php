<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vaccine_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('vaccine_name'); // Generic vaccine name (e.g., "BCG", "OPV")
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false); // Primary product for this vaccine
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vaccine_name', 'product_id']);
            $table->index('vaccine_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vaccine_product_mappings');
    }
};
