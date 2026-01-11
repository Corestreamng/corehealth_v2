<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add audit_details column to shift_handovers table
 * 
 * Stores detailed audit log changes (old/new values) for comprehensive handover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->json('audit_details')->nullable()
                ->after('action_summary')
                ->comment('Detailed audit log changes with old/new values');
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn('audit_details');
        });
    }
};
