<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('checklist_templates')) {
            DB::statement("ALTER TABLE checklist_templates MODIFY COLUMN type VARCHAR(50) NOT NULL COMMENT 'Checklist type (admission, discharge, surgical, procedure)'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('checklist_templates')) {
            DB::statement("ALTER TABLE checklist_templates MODIFY COLUMN type ENUM('admission', 'discharge') NOT NULL COMMENT 'Checklist type'");
        }
    }
};
