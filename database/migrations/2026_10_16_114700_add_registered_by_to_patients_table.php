<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedBigInteger('registered_by')->nullable()->after('registration_workbench')->index();
            $table->foreign('registered_by')->references('id')->on('users')->nullOnDelete();
        });

        // Retroactively backfill registered_by from OwenIt audits table where available
        try {
            DB::statement("
                UPDATE patients p
                INNER JOIN audits a ON a.auditable_id = p.id
                    AND a.auditable_type LIKE '%Patient'
                    AND a.event = 'created'
                    AND a.user_id IS NOT NULL
                SET p.registered_by = a.user_id
                WHERE p.registered_by IS NULL
            ");
        } catch (\Throwable $e) {
            // Silently continue if audit table structure differs in test environments
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['registered_by']);
            $table->dropColumn('registered_by');
        });
    }
};
