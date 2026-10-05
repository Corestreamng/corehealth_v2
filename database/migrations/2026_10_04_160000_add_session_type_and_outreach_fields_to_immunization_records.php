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
        // Make patient_id nullable so community outreach tally records can be saved without requiring patient accounts
        DB::statement('ALTER TABLE immunization_records MODIFY patient_id BIGINT UNSIGNED NULL;');

        Schema::table('immunization_records', function (Blueprint $table) {
            if (!Schema::hasColumn('immunization_records', 'session_type')) {
                $table->string('session_type', 50)->default('fixed')->after('dose');
                $table->index('session_type');
            }
            if (!Schema::hasColumn('immunization_records', 'headcount')) {
                $table->unsignedInteger('headcount')->default(1)->after('session_type');
            }
            if (!Schema::hasColumn('immunization_records', 'age_group')) {
                $table->string('age_group', 50)->nullable()->after('headcount');
            }
            if (!Schema::hasColumn('immunization_records', 'target_group')) {
                $table->string('target_group', 50)->nullable()->after('age_group');
            }
            if (!Schema::hasColumn('immunization_records', 'gender')) {
                $table->string('gender', 20)->nullable()->after('target_group');
            }
            if (!Schema::hasColumn('immunization_records', 'location_settlement')) {
                $table->string('location_settlement', 255)->nullable()->after('site');
            }
            if (!Schema::hasColumn('immunization_records', 'outreach_session_id')) {
                $table->string('outreach_session_id', 100)->nullable()->after('location_settlement');
                $table->index('outreach_session_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('immunization_records', function (Blueprint $table) {
            if (Schema::hasColumn('immunization_records', 'outreach_session_id')) {
                $table->dropIndex(['outreach_session_id']);
                $table->dropColumn('outreach_session_id');
            }
            if (Schema::hasColumn('immunization_records', 'location_settlement')) {
                $table->dropColumn('location_settlement');
            }
            if (Schema::hasColumn('immunization_records', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('immunization_records', 'target_group')) {
                $table->dropColumn('target_group');
            }
            if (Schema::hasColumn('immunization_records', 'age_group')) {
                $table->dropColumn('age_group');
            }
            if (Schema::hasColumn('immunization_records', 'headcount')) {
                $table->dropColumn('headcount');
            }
            if (Schema::hasColumn('immunization_records', 'session_type')) {
                $table->dropIndex(['session_type']);
                $table->dropColumn('session_type');
            }
        });

        DB::statement('ALTER TABLE immunization_records MODIFY patient_id BIGINT UNSIGNED NOT NULL;');
    }
};
