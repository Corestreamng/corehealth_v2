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
        // 1. Add configurable outcome flags and service_type to nhmis_service_mappings
        Schema::table('nhmis_service_mappings', function (Blueprint $table) {
            $table->string('service_type', 30)->default('investigation')->after('service_id'); // investigation, imaging, procedure
            $table->json('supported_outcomes')->nullable()->after('notes');
            $table->json('positive_outcomes')->nullable()->after('supported_outcomes');
        });

        // 2. Add standardized classification columns to lab_service_requests
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->string('nhmis_outcome', 50)->nullable()->index()->after('result_date');
            $table->string('nhmis_outcome_raw', 100)->nullable()->after('nhmis_outcome');
            $table->timestamp('nhmis_classified_at')->nullable()->after('nhmis_outcome_raw');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropIndex(['nhmis_outcome']);
            $table->dropColumn(['nhmis_outcome', 'nhmis_outcome_raw', 'nhmis_classified_at']);
        });

        Schema::table('nhmis_service_mappings', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'supported_outcomes', 'positive_outcomes']);
        });
    }
};
