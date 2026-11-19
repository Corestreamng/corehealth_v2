<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('nhmis_product_mappings')) {
            Schema::create('nhmis_product_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('indicator_code', 60)->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->unique(['indicator_code', 'product_id']);
            });
        }

        // Start from the same products the form counted before links existed, so existing
        // facilities keep their numbers; staff then review the links in Service Links.
        try {
            \App\Models\NhmisProductMapping::autoPopulateDefaults();
        } catch (\Throwable $e) {
            // Products table may be empty on a fresh install; links can be added later.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nhmis_product_mappings');
    }
};
