<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('immunization_records', function (Blueprint $table) {
            if (!Schema::hasColumn('immunization_records', 'stock_source')) {
                $table->string('stock_source', 50)->default('govt_epi')->after('session_type');
                $table->index('stock_source');
            }
            if (!Schema::hasColumn('immunization_records', 'cold_chain_carrier')) {
                $table->string('cold_chain_carrier', 100)->nullable()->after('location_settlement');
            }
            if (!Schema::hasColumn('immunization_records', 'vvm_stage')) {
                $table->string('vvm_stage', 20)->nullable()->after('cold_chain_carrier');
            }
            if (!Schema::hasColumn('immunization_records', 'doses_wasted')) {
                $table->unsignedInteger('doses_wasted')->default(0)->after('headcount');
            }
            if (!Schema::hasColumn('immunization_records', 'auto_deduct_stock')) {
                $table->boolean('auto_deduct_stock')->default(false)->after('dispensed_from_store_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('immunization_records', function (Blueprint $table) {
            if (Schema::hasColumn('immunization_records', 'auto_deduct_stock')) {
                $table->dropColumn('auto_deduct_stock');
            }
            if (Schema::hasColumn('immunization_records', 'doses_wasted')) {
                $table->dropColumn('doses_wasted');
            }
            if (Schema::hasColumn('immunization_records', 'vvm_stage')) {
                $table->dropColumn('vvm_stage');
            }
            if (Schema::hasColumn('immunization_records', 'cold_chain_carrier')) {
                $table->dropColumn('cold_chain_carrier');
            }
            if (Schema::hasColumn('immunization_records', 'stock_source')) {
                $table->dropIndex(['stock_source']);
                $table->dropColumn('stock_source');
            }
        });
    }
};
