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
        if (Schema::hasTable('imaging_service_requests')) {
            Schema::table('imaging_service_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('imaging_service_requests', 'nhmis_outcome')) {
                    $table->string('nhmis_outcome', 50)->nullable()->index()->after('result_date');
                }
                if (!Schema::hasColumn('imaging_service_requests', 'nhmis_outcome_raw')) {
                    $table->string('nhmis_outcome_raw', 100)->nullable()->after('nhmis_outcome');
                }
                if (!Schema::hasColumn('imaging_service_requests', 'nhmis_classified_at')) {
                    $table->timestamp('nhmis_classified_at')->nullable()->after('nhmis_outcome_raw');
                }
            });
        }

        if (Schema::hasTable('procedures')) {
            Schema::table('procedures', function (Blueprint $table) {
                if (!Schema::hasColumn('procedures', 'nhmis_outcome')) {
                    $table->string('nhmis_outcome', 50)->nullable()->index()->after('outcome');
                }
                if (!Schema::hasColumn('procedures', 'nhmis_outcome_raw')) {
                    $table->string('nhmis_outcome_raw', 100)->nullable()->after('nhmis_outcome');
                }
                if (!Schema::hasColumn('procedures', 'nhmis_classified_at')) {
                    $table->timestamp('nhmis_classified_at')->nullable()->after('nhmis_outcome_raw');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('imaging_service_requests')) {
            Schema::table('imaging_service_requests', function (Blueprint $table) {
                if (Schema::hasColumn('imaging_service_requests', 'nhmis_outcome')) {
                    $table->dropIndex(['nhmis_outcome']);
                    $table->dropColumn(['nhmis_outcome', 'nhmis_outcome_raw', 'nhmis_classified_at']);
                }
            });
        }

        if (Schema::hasTable('procedures')) {
            Schema::table('procedures', function (Blueprint $table) {
                if (Schema::hasColumn('procedures', 'nhmis_outcome')) {
                    $table->dropIndex(['nhmis_outcome']);
                    $table->dropColumn(['nhmis_outcome', 'nhmis_outcome_raw', 'nhmis_classified_at']);
                }
            });
        }
    }
};
