<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medical_reports') && !Schema::hasColumn('medical_reports', 'deleted_at')) {
            DB::statement("ALTER TABLE medical_reports ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('medical_reports', 'deleted_at')) {
            DB::statement("ALTER TABLE medical_reports DROP COLUMN deleted_at");
        }
    }
};
