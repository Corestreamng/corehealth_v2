<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imaging dismissals now record when, by whom and why (the laboratory already does), so the Dismissed list can
 * show them and a restore can return the request to the stage it was at. Additive, nullable columns only.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('imaging_service_requests', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'dismissed_by')) {
                $table->unsignedBigInteger('dismissed_by')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'dismiss_reason')) {
                $table->text('dismiss_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive only: never dropped from a production table.
    }
};
