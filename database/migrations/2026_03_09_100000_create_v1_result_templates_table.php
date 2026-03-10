<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create v1_result_templates table for reusable V1 HTML result templates.
     * These templates can be inserted into the WYSIWYG editor in lab/imaging result entry.
     * They are NOT tied to any specific investigation — user picks one and it populates the editor.
     */
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS v1_result_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL COMMENT 'Template display name e.g. Haematology (FBC, Genotype, etc.)',
                description VARCHAR(500) NULL COMMENT 'Brief description of what the template covers',
                content LONGTEXT NOT NULL COMMENT 'HTML content for CKEditor WYSIWYG editor',
                category VARCHAR(100) NOT NULL DEFAULT 'General' COMMENT 'Category for grouping: Haematology, Chemistry, etc.',
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL COMMENT 'User who created the template',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
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
        DB::statement("DROP TABLE IF EXISTS v1_result_templates");
    }
};
