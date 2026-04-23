<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds type_filter column to store_context_rules for the new 'type_bucket' rule type.
 *
 * A type_bucket rule maps a Spatie role to a whole class of stores rather than
 * a single store.  Values: 'all' | 'pharmacy' | 'ward' | 'department'
 *
 * Plan Option B — candidateStores() DB-driven type expansion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_context_rules', function (Blueprint $table) {
            // Allowed store-class when rule_type = 'type_bucket'
            // all | pharmacy | ward | department
            $table->string('type_filter', 30)->nullable()->after('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_context_rules', function (Blueprint $table) {
            $table->dropColumn('type_filter');
        });
    }
};
