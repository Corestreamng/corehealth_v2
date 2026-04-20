<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_qualifications', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_qualifications', 'date_obtained')) {
                $table->date('date_obtained')->nullable()->after('year_of_graduation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_qualifications', function (Blueprint $table) {
            $table->dropColumn('date_obtained');
        });
    }
};
