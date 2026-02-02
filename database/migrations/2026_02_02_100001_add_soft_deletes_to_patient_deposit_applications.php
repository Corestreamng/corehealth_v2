<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_deposit_applications', function (Blueprint $table) {
            // Add soft deletes if not exists
            if (!Schema::hasColumn('patient_deposit_applications', 'deleted_at')) {
                $table->softDeletes();
            }

            // Also add notes column if not exists
            if (!Schema::hasColumn('patient_deposit_applications', 'notes')) {
                $table->text('notes')->nullable()->after('reversed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_deposit_applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('notes');
        });
    }
};
