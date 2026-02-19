<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create medical_reports table for doctor-generated medical reports.
     * Supports WYSIWYG-authored content, finalization, and print output.
     */
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS medical_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                patient_id BIGINT UNSIGNED NOT NULL,
                encounter_id BIGINT UNSIGNED NULL,
                doctor_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT 'Medical Report',
                content LONGTEXT NOT NULL COMMENT 'HTML content from WYSIWYG editor',
                report_date DATE NOT NULL,
                status ENUM('draft', 'finalized') NOT NULL DEFAULT 'draft',
                finalized_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_patient_id (patient_id),
                INDEX idx_doctor_id (doctor_id),
                INDEX idx_encounter_id (encounter_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS medical_reports");
    }
};
