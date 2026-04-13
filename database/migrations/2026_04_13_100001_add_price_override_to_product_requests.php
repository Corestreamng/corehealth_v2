<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->decimal('price_override', 12, 2)->nullable()->after('qty_adjusted_by')
                ->comment('Pre-billing unit price override (per unit)');
            $table->decimal('price_original', 12, 2)->nullable()->after('price_override')
                ->comment('Original unit price before override');
            $table->text('price_override_reason')->nullable()->after('price_original');
            $table->unsignedBigInteger('price_override_by')->nullable()->after('price_override_reason');
            $table->timestamp('price_override_at')->nullable()->after('price_override_by');

            $table->foreign('price_override_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropForeign(['price_override_by']);
            $table->dropColumn([
                'price_override',
                'price_original',
                'price_override_reason',
                'price_override_by',
                'price_override_at',
            ]);
        });
    }
};
