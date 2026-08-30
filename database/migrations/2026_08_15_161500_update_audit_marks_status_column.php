<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \DB::statement("ALTER TABLE audit_marks MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'audited'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE audit_marks MODIFY COLUMN status ENUM('audited', 'queried') NOT NULL");
    }
};
