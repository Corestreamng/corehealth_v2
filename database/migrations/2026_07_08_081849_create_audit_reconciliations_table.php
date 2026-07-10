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
        Schema::create('audit_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('audit_stamp_id')->nullable()->constrained('audit_stamps');
            $table->decimal('system_value', 15, 2);
            $table->decimal('physical_value', 15, 2);
            $table->decimal('variance', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('auditor_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_reconciliations');
    }
};
