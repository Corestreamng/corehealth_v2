<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     * Adds composite indexes required by the Encounter Intelligence Workbench.
     * Existing single-column indexes: enc_patient_created_idx, enc_created_idx,
     * enc_doctor_idx — already present from 2026_05_29_000001 migration.
     */
    public function up(): void
    {
        // ── encounters: composite for workbench list + clinic analytics ──────
        // Supports: ORDER BY created_at + filter by doctor_id + queue_id
        $this->addIndexIfMissing(
            'encounters',
            ['created_at', 'doctor_id', 'queue_id'],
            'enc_workbench_created_doctor_queue_idx'
        );

        // Supports: completed filter + created_at range
        $this->addIndexIfMissing(
            'encounters',
            ['completed', 'created_at'],
            'enc_workbench_completed_created_idx'
        );

        // ── lab_service_requests: per-encounter aggregation ──────────────────
        // Supports: COUNT labs per encounter
        $this->addIndexIfMissing(
            'lab_service_requests',
            ['encounter_id', 'status'],
            'lsr_encounter_status_idx'
        );

        // ── imaging_service_requests: per-encounter aggregation ──────────────
        $this->addIndexIfMissing(
            'imaging_service_requests',
            ['encounter_id', 'status'],
            'isr_encounter_status_idx'
        );

        // ── procedures: per-encounter aggregation ────────────────────────────
        if (Schema::hasTable('procedures')) {
            $this->addIndexIfMissing(
                'procedures',
                ['encounter_id', 'procedure_status'],
                'proc_encounter_status_idx'
            );
        }

        // ── specialist_referrals: per-encounter aggregation ──────────────────
        if (Schema::hasTable('specialist_referrals')) {
            $this->addIndexIfMissing(
                'specialist_referrals',
                ['encounter_id', 'status'],
                'sref_encounter_status_idx'
            );
        }

        // ── product_or_service_requests: revenue per encounter ───────────────
        // Supports: SUM(amount) GROUP BY encounter_id, filter by validation_status
        $this->addIndexIfMissing(
            'product_or_service_requests',
            ['encounter_id', 'validation_status'],
            'posr_encounter_validation_status_idx'
        );

        // ── doctor_queues: clinic lookups from encounter workbench ───────────
        $this->addIndexIfMissing(
            'doctor_queues',
            ['clinic_id', 'created_at'],
            'dq_clinic_created_idx'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('encounters', 'enc_workbench_created_doctor_queue_idx');
        $this->dropIndexIfExists('encounters', 'enc_workbench_completed_created_idx');
        $this->dropIndexIfExists('lab_service_requests', 'lsr_encounter_status_idx');
        $this->dropIndexIfExists('imaging_service_requests', 'isr_encounter_status_idx');

        if (Schema::hasTable('procedures')) {
            $this->dropIndexIfExists('procedures', 'proc_encounter_status_idx');
        }

        if (Schema::hasTable('specialist_referrals')) {
            $this->dropIndexIfExists('specialist_referrals', 'sref_encounter_status_idx');
        }

        $this->dropIndexIfExists('product_or_service_requests', 'posr_encounter_validation_status_idx');
        $this->dropIndexIfExists('doctor_queues', 'dq_clinic_created_idx');
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return !empty($indexes);
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if (!$this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }
};
