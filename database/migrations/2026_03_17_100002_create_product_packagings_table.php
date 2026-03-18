<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->tinyInteger('level')->unsigned()->default(1);
            $table->foreignId('parent_packaging_id')->nullable()->constrained('product_packagings')->nullOnDelete();
            $table->decimal('units_in_parent', 12, 4)->unsigned()->default(1);
            $table->decimal('base_unit_qty', 12, 4)->unsigned()->default(1);
            $table->boolean('is_default_purchase')->default(false);
            $table->boolean('is_default_dispense')->default(false);
            $table->string('barcode', 100)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'name'], 'uq_product_packaging_name');
            $table->unique(['product_id', 'level'], 'uq_product_packaging_level');
            $table->index('product_id', 'idx_product_packagings_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packagings');
    }
};
