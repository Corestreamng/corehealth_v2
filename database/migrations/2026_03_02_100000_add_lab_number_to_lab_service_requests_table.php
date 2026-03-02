<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->string('lab_number', 50)->nullable()->after('sample_taken_by')
                  ->index('idx_lab_number');
        });
    }

    public function down(): void
    {
        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropIndex('idx_lab_number');
            $table->dropColumn('lab_number');
        });
    }
};
