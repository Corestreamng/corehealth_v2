<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create diagnosis_favorites table for doctors to save reusable diagnosis sets.
     * Supports per-diagnosis comments (status/course) stored as JSON.
     */
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS diagnosis_favorites (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                doctor_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                diagnoses JSON NOT NULL COMMENT 'Array of {code, name, comment_1, comment_2}',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_doctor_id (doctor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS diagnosis_favorites');
    }
};
