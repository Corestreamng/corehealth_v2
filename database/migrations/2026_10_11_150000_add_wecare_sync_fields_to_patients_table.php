<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add WeCare247 bidirectional sync fields to patients table.
     *
     * - wecare_patient_id   : MongoDB ObjectId of the patient in WeCare247
     * - wecare_sync_uuid    : Deterministic sha1(facility_id:patient_id:file_no)
     * - wecare_sync_active  : Master toggle — 0 = unlinked, 1 = active sync
     * - wecare_synced_at    : Timestamp of the most recent successful sync cycle
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('wecare_patient_id', 64)->nullable()->after('file_no')
                  ->comment('WeCare247 MongoDB user _id');
            $table->char('wecare_sync_uuid', 40)->nullable()->after('wecare_patient_id')
                  ->comment('Deterministic sha1 composite sync key');
            $table->tinyInteger('wecare_sync_active')->unsigned()->default(0)->after('wecare_sync_uuid')
                  ->comment('0=unlinked, 1=active sync');
            $table->timestamp('wecare_synced_at')->nullable()->after('wecare_sync_active')
                  ->comment('Timestamp of last successful sync');

            // Index for fast reverse-lookup from WeCare247 callbacks
            $table->index('wecare_patient_id', 'idx_patients_wecare_id');
            $table->index('wecare_sync_uuid', 'idx_patients_wecare_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_patients_wecare_id');
            $table->dropIndex('idx_patients_wecare_uuid');
            $table->dropColumn(['wecare_patient_id', 'wecare_sync_uuid', 'wecare_sync_active', 'wecare_synced_at']);
        });
    }
};
