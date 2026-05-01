<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Change fetal_heart_rate to string in anc_visits
        DB::statement('ALTER TABLE anc_visits MODIFY fetal_heart_rate VARCHAR(255) NULL');

        // 2. Change foetal_heart_rate to string in delivery_partograph
        DB::statement('ALTER TABLE delivery_partograph MODIFY foetal_heart_rate VARCHAR(255) NULL');

        // 3. Add created_by to maternity_medical_history
        Schema::table('maternity_medical_history', function (Blueprint $table) {
            if (!Schema::hasColumn('maternity_medical_history', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE anc_visits MODIFY fetal_heart_rate SMALLINT NULL');
        DB::statement('ALTER TABLE delivery_partograph MODIFY foetal_heart_rate SMALLINT NULL');

        Schema::table('maternity_medical_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
