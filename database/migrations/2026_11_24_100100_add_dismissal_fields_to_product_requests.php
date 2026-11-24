<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacy dismissals record when, by whom and why (as the laboratory does), so the Dismissed list can show them and a
 * restore can return the prescription to the stage it was at. Additive, nullable columns only.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('product_requests', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable();
            }
            if (!Schema::hasColumn('product_requests', 'dismissed_by')) {
                $table->unsignedBigInteger('dismissed_by')->nullable();
            }
            if (!Schema::hasColumn('product_requests', 'dismiss_reason')) {
                $table->text('dismiss_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive only: never dropped from a production table.
    }
};
