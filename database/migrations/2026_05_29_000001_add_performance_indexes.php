<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Optimization: Add missing indexes to high-traffic tables.
 *
 * These 13 tables had zero or minimal indexes and are queried thousands of
 * times per minute across dashboard services and workbench controllers.
 *
 * Run: php artisan migrate
 *
 * NOTE: On very large tables this may take a few seconds per table.
 * InnoDB performs online DDL so the tables remain readable during indexing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── doctor_queues ─────────────────────────────────────────────
        // Used by: Reception, Nursing, Doctor, Dashboard (8-12 queries/request)
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'dq_status_created_idx');
            $table->index(['patient_id', 'status', 'created_at'], 'dq_patient_status_created_idx');
            $table->index(['clinic_id', 'status', 'created_at'], 'dq_clinic_status_created_idx');
            $table->index(['priority', 'status'], 'dq_priority_status_idx');
        });

        // ── product_or_service_requests ───────────────────────────────
        // Used by: Billing, HMO, Pharmacy, Accounts, Reception (15-25 queries/request)
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->index(['payment_id', 'created_at'], 'posr_payment_created_idx');
            $table->index(['user_id', 'payment_id'], 'posr_user_payment_idx');
            $table->index(['user_id', 'validation_status', 'coverage_mode'], 'posr_user_validation_coverage_idx');
            $table->index(['validation_status', 'coverage_mode', 'claims_amount'], 'posr_validation_coverage_claims_idx');
            $table->index(['validation_status', 'validated_at'], 'posr_validation_validated_idx');
            $table->index('encounter_id', 'posr_encounter_idx');
            $table->index('hmo_id', 'posr_hmo_idx');
        });

        // ── product_requests ──────────────────────────────────────────
        // Used by: Pharmacy (8-15 queries/request)
        Schema::table('product_requests', function (Blueprint $table) {
            $table->index(['status', 'patient_id'], 'pr_status_patient_idx');
            $table->index(['patient_id', 'status', 'created_at'], 'pr_patient_status_created_idx');
            $table->index(['status', 'created_at'], 'pr_status_created_idx');
        });

        // ── admission_requests ────────────────────────────────────────
        // Used by: Nursing, Reception, Dashboard (6-10 queries/request)
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->index(['discharged', 'bed_id'], 'ar_discharged_bed_idx');
            $table->index(['discharged', 'priority'], 'ar_discharged_priority_idx');
            $table->index(['patient_id', 'discharged'], 'ar_patient_discharged_idx');
            $table->index('admission_status', 'ar_admission_status_idx');
        });

        // ── medication_schedules ──────────────────────────────────────
        // Used by: Nursing (5-8 queries per patient, N+1 hotspot)
        Schema::table('medication_schedules', function (Blueprint $table) {
            $table->index(['patient_id', 'scheduled_time'], 'ms_patient_scheduled_idx');
            $table->index('scheduled_time', 'ms_scheduled_time_idx');
        });

        // ── medication_administrations ────────────────────────────────
        // Used by: Nursing (3-6 queries per patient for admin checks)
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->index(['schedule_id', 'deleted_at'], 'ma_schedule_deleted_idx');
            $table->index(['patient_id', 'administered_at'], 'ma_patient_admin_idx');
        });

        // ── vital_signs ───────────────────────────────────────────────
        // Used by: Nursing, Dashboard (3-5 queries/request)
        if (Schema::hasTable('vital_signs')) {
            Schema::table('vital_signs', function (Blueprint $table) {
                $table->index(['patient_id', 'created_at'], 'vs_patient_created_idx');
            });
        }

        // ── lab_service_requests ──────────────────────────────────────
        // Used by: Lab, Dashboard (6-8 queries/request)
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'lsr_status_created_idx');
            $table->index(['status', 'updated_at'], 'lsr_status_updated_idx');
            $table->index(['patient_id', 'status'], 'lsr_patient_status_idx');
            $table->index('priority', 'lsr_priority_idx');
        });

        // ── imaging_service_requests ──────────────────────────────────
        // Used by: Imaging, Dashboard (4-6 queries/request)
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'isr_status_created_idx');
            $table->index(['patient_id', 'status'], 'isr_patient_status_idx');
            $table->index('priority', 'isr_priority_idx');
        });

        // ── payments ──────────────────────────────────────────────────
        // Used by: Billing, Accounts, Dashboard (4-8 queries/request)
        Schema::table('payments', function (Blueprint $table) {
            $table->index('created_at', 'pay_created_idx');
            $table->index(['payment_method', 'created_at'], 'pay_method_created_idx');
            $table->index('patient_id', 'pay_patient_idx');
        });

        // ── encounters ────────────────────────────────────────────────
        // Used by: Doctor, Reception, Dashboard (3-6 queries/request)
        Schema::table('encounters', function (Blueprint $table) {
            $table->index(['patient_id', 'created_at'], 'enc_patient_created_idx');
            $table->index('created_at', 'enc_created_idx');
            $table->index('doctor_id', 'enc_doctor_idx');
        });

        // ── nurse_notes ───────────────────────────────────────────────
        // Used by: Nursing (2-3 queries/request)
        if (Schema::hasTable('nurse_notes')) {
            Schema::table('nurse_notes', function (Blueprint $table) {
                $table->index(['patient_id', 'created_at'], 'nn_patient_created_idx');
                $table->index(['note_type', 'created_at'], 'nn_type_created_idx');
            });
        }

        // ── audits ────────────────────────────────────────────────────
        // Used by: Audit dashboard (2-4 queries/request)
        if (Schema::hasTable('audits')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->index('created_at', 'aud_created_idx');
                $table->index('user_id', 'aud_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('doctor_queues', function (Blueprint $table) {
            $table->dropIndex('dq_status_created_idx');
            $table->dropIndex('dq_patient_status_created_idx');
            $table->dropIndex('dq_clinic_status_created_idx');
            $table->dropIndex('dq_priority_status_idx');
        });

        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropIndex('posr_payment_created_idx');
            $table->dropIndex('posr_user_payment_idx');
            $table->dropIndex('posr_user_validation_coverage_idx');
            $table->dropIndex('posr_validation_coverage_claims_idx');
            $table->dropIndex('posr_validation_validated_idx');
            $table->dropIndex('posr_encounter_idx');
            $table->dropIndex('posr_hmo_idx');
        });

        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropIndex('pr_status_patient_idx');
            $table->dropIndex('pr_patient_status_created_idx');
            $table->dropIndex('pr_status_created_idx');
        });

        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropIndex('ar_discharged_bed_idx');
            $table->dropIndex('ar_discharged_priority_idx');
            $table->dropIndex('ar_patient_discharged_idx');
            $table->dropIndex('ar_admission_status_idx');
        });

        Schema::table('medication_schedules', function (Blueprint $table) {
            $table->dropIndex('ms_patient_scheduled_idx');
            $table->dropIndex('ms_scheduled_time_idx');
        });

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropIndex('ma_schedule_deleted_idx');
            $table->dropIndex('ma_patient_admin_idx');
        });

        if (Schema::hasTable('vital_signs')) {
            Schema::table('vital_signs', function (Blueprint $table) {
                $table->dropIndex('vs_patient_created_idx');
            });
        }

        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropIndex('lsr_status_created_idx');
            $table->dropIndex('lsr_status_updated_idx');
            $table->dropIndex('lsr_patient_status_idx');
            $table->dropIndex('lsr_priority_idx');
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropIndex('isr_status_created_idx');
            $table->dropIndex('isr_patient_status_idx');
            $table->dropIndex('isr_priority_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('pay_created_idx');
            $table->dropIndex('pay_method_created_idx');
            $table->dropIndex('pay_patient_idx');
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->dropIndex('enc_patient_created_idx');
            $table->dropIndex('enc_created_idx');
            $table->dropIndex('enc_doctor_idx');
        });

        if (Schema::hasTable('nurse_notes')) {
            Schema::table('nurse_notes', function (Blueprint $table) {
                $table->dropIndex('nn_patient_created_idx');
                $table->dropIndex('nn_type_created_idx');
            });
        }

        if (Schema::hasTable('audits')) {
            Schema::table('audits', function (Blueprint $table) {
                $table->dropIndex('aud_created_idx');
                $table->dropIndex('aud_user_idx');
            });
        }
    }
};
