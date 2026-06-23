<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds the columns required for the Maternity Consultation Queue feature:
     *  1. anc_visits.visit_type_sub   — distinguishes nurse entry vs doctor consultation
     *  2. doctor_queues.maternity_enrollment_id — links a queue entry to a maternity enrollment
     *  3. Extend anc_visits.visit_type enum to support 'doctor_consultation'
     */
    public function up(): void
    {
        // ── 1. anc_visits: add visit_type_sub ───────────────────────────────
        Schema::table('anc_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('anc_visits', 'visit_type_sub')) {
                $table->enum('visit_type_sub', ['nurse_entry', 'doctor_consultation'])
                    ->default('nurse_entry')
                    ->nullable()
                    ->after('visit_type')
                    ->comment('nurse_entry = nurse physical checks only; doctor_consultation = doctor saw the patient, linked to an encounter');
            }

            // seen_by is currently NOT NULL — make nullable so nurse-only visits can have no user FK
            // (existing data is fine; this just prevents edge-case failures)
            // Only alter if needed — wrap in try/catch as some setups already have it nullable
        });

        // ── 2. anc_visits: extend visit_type enum to include doctor_consultation
        //    We use a raw ALTER because Laravel Blueprint can't easily extend enums
        DB::statement("ALTER TABLE anc_visits MODIFY COLUMN visit_type ENUM('booking','routine','emergency','specialist_referral','doctor_consultation') NOT NULL DEFAULT 'routine'");

        // ── 3. doctor_queues: add maternity_enrollment_id ───────────────────
        Schema::table('doctor_queues', function (Blueprint $table) {
            if (!Schema::hasColumn('doctor_queues', 'maternity_enrollment_id')) {
                $table->unsignedBigInteger('maternity_enrollment_id')
                    ->nullable()
                    ->after('appointment_id')
                    ->comment('Links this queue entry to a maternity enrollment for ANC consultation flow');

                $table->foreign('maternity_enrollment_id')
                    ->references('id')
                    ->on('maternity_enrollments')
                    ->nullOnDelete();
            }
        });

        // ── 4. Add index for fast maternity queue lookups ───────────────────
        Schema::table('doctor_queues', function (Blueprint $table) {
            // Only add if not already present
            try {
                $table->index(['maternity_enrollment_id', 'status'], 'idx_dq_maternity_status');
            } catch (\Exception $e) {
                // Index already exists — skip
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_queues', function (Blueprint $table) {
            if (Schema::hasColumn('doctor_queues', 'maternity_enrollment_id')) {
                $table->dropForeign(['maternity_enrollment_id']);
                try {
                    $table->dropIndex('idx_dq_maternity_status');
                } catch (\Exception $e) {}
                $table->dropColumn('maternity_enrollment_id');
            }
        });

        Schema::table('anc_visits', function (Blueprint $table) {
            if (Schema::hasColumn('anc_visits', 'visit_type_sub')) {
                $table->dropColumn('visit_type_sub');
            }
        });

        // Revert visit_type enum
        DB::statement("ALTER TABLE anc_visits MODIFY COLUMN visit_type ENUM('booking','routine','emergency','specialist_referral') NOT NULL DEFAULT 'routine'");
    }
};
