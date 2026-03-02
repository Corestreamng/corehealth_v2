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
        Schema::table('wards', function (Blueprint $table) {
            if (!Schema::hasColumn('wards', 'bed_price')) {
                $table->decimal('bed_price', 12, 2)->default(0)->after('capacity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            if (Schema::hasColumn('wards', 'bed_price')) {
                $table->dropColumn('bed_price');
            }
        });
    }
};
