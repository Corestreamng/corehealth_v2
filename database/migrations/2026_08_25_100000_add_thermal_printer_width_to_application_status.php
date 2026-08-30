<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->string('thermal_printer_width', 10)->default('w80')->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('application_status', function (Blueprint $table) {
            $table->dropColumn('thermal_printer_width');
        });
    }
};
