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
        // 1. Add start_date and end_date to nhmis_monthly_reports for custom date ranges
        Schema::table('nhmis_monthly_reports', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('month');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('period_type', 20)->default('monthly')->after('end_date'); // monthly, annual, custom
        });

        // 2. Create nhmis_service_mappings table for facility service delegations
        Schema::create('nhmis_service_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_code', 100)->index(); // e.g. malaria_microscopy, malaria_rdt, hiv_screening, syphilis_vdrl
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['indicator_code', 'service_id'], 'nhmis_indicator_service_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nhmis_service_mappings');

        Schema::table('nhmis_monthly_reports', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'period_type']);
        });
    }
};
