<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create clinic_note_templates table for reusable clinical note templates by clinic.
     * Templates can be used by doctors to pre-fill clinical notes with standard content.
     */
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS clinic_note_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                clinic_id BIGINT UNSIGNED NULL COMMENT 'NULL = global template available to all clinics',
                name VARCHAR(255) NOT NULL,
                description VARCHAR(500) NULL,
                content LONGTEXT NOT NULL COMMENT 'HTML content for CKEditor',
                category VARCHAR(100) NULL DEFAULT 'General' COMMENT 'Template category for grouping',
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL COMMENT 'User who created the template',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_clinic_id (clinic_id),
                INDEX idx_category (category),
                INDEX idx_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS clinic_note_templates");
    }
};
