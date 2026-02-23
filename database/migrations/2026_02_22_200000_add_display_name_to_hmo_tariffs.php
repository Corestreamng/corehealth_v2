<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hmo_tariffs', function (Blueprint $table) {
            $table->string('display_name', 255)->nullable()->after('coverage_mode')
                  ->comment('Override name shown in claims reports and validation records; falls back to product_name/service_name when null');
        });
    }

    public function down(): void
    {
        Schema::table('hmo_tariffs', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
