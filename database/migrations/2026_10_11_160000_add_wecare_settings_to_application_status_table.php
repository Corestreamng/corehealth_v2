<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds WeCare247 integration configuration fields and master killswitch to application_status.
     */
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status', 'wecare_enabled')) {
                $table->boolean('wecare_enabled')->default(false)->after('active')->comment('Master switch for WeCare247 integration (1=Active, 0=Disabled)');
            }
            if (!Schema::hasColumn('application_status', 'wecare_url')) {
                $table->string('wecare_url', 255)->nullable()->after('wecare_enabled')->comment('Base URL of the WeCare247 platform instance');
            }
            if (!Schema::hasColumn('application_status', 'wecare_facility_id')) {
                $table->string('wecare_facility_id', 64)->nullable()->after('wecare_url')->comment('Facility ID / UUID in WeCare247');
            }
            if (!Schema::hasColumn('application_status', 'wecare_api_key')) {
                $table->string('wecare_api_key', 255)->nullable()->after('wecare_facility_id')->comment('WeCare247 API key / Webhook signing secret');
            }
            if (!Schema::hasColumn('application_status', 'wecare_auto_sync_encounters')) {
                $table->boolean('wecare_auto_sync_encounters')->default(true)->after('wecare_api_key')->comment('Auto-ingest telehealth consultation notes into longitudinal EHR');
            }
            if (!Schema::hasColumn('application_status', 'wecare_auto_sync_labs')) {
                $table->boolean('wecare_auto_sync_labs')->default(true)->after('wecare_auto_sync_encounters')->comment('Auto-push verified lab results to WeCare247 portal');
            }
            if (!Schema::hasColumn('application_status', 'wecare_allow_patient_lookup')) {
                $table->boolean('wecare_allow_patient_lookup')->default(true)->after('wecare_auto_sync_labs')->comment('Allow WeCare247 facility admin to lookup patient demographic records');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $columns = [
                'wecare_enabled',
                'wecare_url',
                'wecare_facility_id',
                'wecare_api_key',
                'wecare_auto_sync_encounters',
                'wecare_auto_sync_labs',
                'wecare_allow_patient_lookup',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('application_status', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
