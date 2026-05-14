<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add template_type column to v1_result_templates.
     * Values: 'lab' | 'imaging' | 'both'
     * Default 'both' so existing templates remain visible everywhere.
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE v1_result_templates
            ADD COLUMN template_type ENUM('lab', 'imaging', 'both') NOT NULL DEFAULT 'lab'
                COMMENT 'Context where this template is shown: lab, imaging, or both'
                AFTER category
        ");

        DB::statement("
            ALTER TABLE v1_result_templates
            ADD INDEX idx_template_type (template_type)
        ");
    }

    public function down()
    {
        DB::statement("ALTER TABLE v1_result_templates DROP INDEX idx_template_type");
        DB::statement("ALTER TABLE v1_result_templates DROP COLUMN template_type");
    }
};
