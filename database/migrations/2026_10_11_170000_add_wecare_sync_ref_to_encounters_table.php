<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add wecare_sync_ref to encounters table for idempotent telehealth sync.
     *
     * - wecare_sync_ref: sha1('wecare_encounter:' . wecare_appointment_id)
     */
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->char('wecare_sync_ref', 40)->nullable()->after('notes')
                  ->comment('SHA-1 encounter deduplication key from WeCare247');

            $table->index('wecare_sync_ref', 'idx_encounters_wecare_sync_ref');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropIndex('idx_encounters_wecare_sync_ref');
            $table->dropColumn('wecare_sync_ref');
        });
    }
};
